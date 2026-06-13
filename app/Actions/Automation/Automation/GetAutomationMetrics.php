<?php

declare(strict_types=1);

namespace App\Actions\Automation\Automation;

use App\Enums\Automation\Run\Status;
use App\Models\Automation;
use App\Models\AutomationRun;
use App\Models\PostPlatform;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class GetAutomationMetrics
{
    /**
     * Aggregate run health and flow output for the Metrics tab over the given
     * (inclusive) date range. App timezone is UTC, so day bucketing aligns with
     * the stored UTC timestamps without conversion.
     *
     * @return array{
     *     totals: array{runs: int, completed: int, failed: int, in_progress: int, success_rate: ?int, avg_duration_ms: ?int, posts_created: int},
     *     timeseries: array<int, array{date: string, started: int, completed: int, failed: int}>,
     *     platforms: array<int, array{platform: string, count: int}>,
     * }
     */
    public function __invoke(Automation $automation, CarbonInterface $start, CarbonInterface $end): array
    {
        $start = $start->copy()->startOfDay();
        $end = $end->copy()->endOfDay();
        $days = (int) $start->diffInDays($end) + 1;

        $runs = $automation->runs()
            ->productionRuns()
            ->whereBetween('created_at', [$start, $end])
            ->get(['id', 'status', 'generated_post_id', 'created_at', 'started_at', 'finished_at']);

        $completed = $runs->where('status', Status::Completed);
        $failed = $runs->where('status', Status::Failed);
        $inProgress = $runs->whereIn('status', [Status::Pending, Status::Running, Status::Waiting]);

        $finished = $completed->count() + $failed->count();
        $successRate = $finished > 0 ? (int) round($completed->count() / $finished * 100) : null;

        $durations = $completed
            ->map(fn ($run) => $run->durationInMilliseconds())
            ->filter(fn ($ms) => $ms !== null);
        $avgDurationMs = $durations->isNotEmpty() ? (int) round($durations->avg()) : null;

        return [
            'totals' => [
                'runs' => $runs->count(),
                'completed' => $completed->count(),
                'failed' => $failed->count(),
                'in_progress' => $inProgress->count(),
                'success_rate' => $successRate,
                'avg_duration_ms' => $avgDurationMs,
                'posts_created' => $runs->whereNotNull('generated_post_id')->count(),
            ],
            'timeseries' => $this->buildTimeseries($runs, $start, $days),
            'platforms' => $this->buildPlatformBreakdown($runs->pluck('generated_post_id')->filter()->all()),
        ];
    }

    /**
     * Zero-filled daily buckets so the chart line stays continuous across days
     * with no runs.
     *
     * @param  Collection<int, AutomationRun>  $runs
     * @return array<int, array{date: string, started: int, completed: int, failed: int}>
     */
    private function buildTimeseries(Collection $runs, CarbonInterface $since, int $days): array
    {
        $series = [];
        for ($i = 0; $i < $days; $i++) {
            $date = $since->copy()->addDays($i)->format('Y-m-d');
            $series[$date] = ['date' => $date, 'started' => 0, 'completed' => 0, 'failed' => 0];
        }

        foreach ($runs as $run) {
            $date = $run->created_at->format('Y-m-d');

            if (! isset($series[$date])) {
                continue;
            }

            $series[$date]['started']++;

            if ($run->status === Status::Completed) {
                $series[$date]['completed']++;
            }

            if ($run->status === Status::Failed) {
                $series[$date]['failed']++;
            }
        }

        return array_values($series);
    }

    /**
     * Count published platform targets across the posts this automation
     * generated, so the chart shows where its output actually went.
     *
     * @param  array<int, string>  $postIds
     * @return array<int, array{platform: string, count: int}>
     */
    private function buildPlatformBreakdown(array $postIds): array
    {
        if ($postIds === []) {
            return [];
        }

        return PostPlatform::query()
            ->whereIn('post_id', $postIds)
            ->selectRaw('platform, count(*) as total')
            ->groupBy('platform')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => ['platform' => $row->platform->value, 'count' => (int) $row->total])
            ->all();
    }
}
