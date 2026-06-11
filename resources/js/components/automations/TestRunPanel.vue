<script setup lang="ts">
import { IconAlertCircle, IconChevronRight, IconCircleCheck, IconCircleDot, IconInfoCircle, IconLoader2, IconPlayerPlay } from '@tabler/icons-vue';
import { trans } from 'laravel-vue-i18n';
import { ref } from 'vue';
import { toast } from 'vue-sonner';

import { showRun as showRunRoute } from '@/actions/App/Http/Controllers/App/AutomationController';
import JsonViewer from '@/components/JsonViewer.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { useAutomationEcho } from '@/composables/echo/useAutomationEcho';
import { test as testAutomation } from '@/routes/app/automations';

interface NodeRun {
    id: string;
    node_id: string;
    node_type: string;
    status: string;
    input: Record<string, unknown> | null;
    output: Record<string, unknown> | null;
    error: { message?: string } | null;
    started_at: string | null;
    finished_at: string | null;
}

interface Run {
    id: string;
    status: string;
    context: Record<string, unknown> | null;
    error: { message?: string } | null;
    started_at: string | null;
    finished_at: string | null;
    is_dry_run: boolean;
}

const props = defineProps<{ automationId: string; beforeRun?: () => Promise<boolean> | boolean }>();

const isStarting = ref(false);
const realData = ref(false);
const run = ref<Run | null>(null);
const nodeRuns = ref<NodeRun[]>([]);
const activeRunId = ref<string | null>(null);

const fetchRun = async (runId: string): Promise<void> => {
    try {
        const response = await fetch(showRunRoute.url({ automation: props.automationId, run: runId }), {
            headers: { Accept: 'application/json' },
        });
        if (!response.ok) return;
        const data = await response.json();
        run.value = data.run;
        nodeRuns.value = data.node_runs;
    } catch {
        // Silent on refresh failures — the next broadcast will retry.
    }
};

// Subscribe once to the automation's private channel. The backend broadcasts
// a tiny `{ run_id, status }` payload on every run/node update; we refetch the
// full state only when the event is for our active run. Zero polling.
useAutomationEcho<{ run_id: string; status: string }>(
    props.automationId,
    '.automation.run.updated',
    (payload) => {
        if (payload.run_id === activeRunId.value) {
            fetchRun(payload.run_id);
        }
    },
);

const start = async () => {
    if (isStarting.value) return;
    isStarting.value = true;
    run.value = null;
    nodeRuns.value = [];
    activeRunId.value = null;

    try {
        const response = await fetch(testAutomation.url(props.automationId), {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null)?.content ?? '',
            },
            body: JSON.stringify({ with_real_data: realData.value }),
        });
        if (!response.ok) {
            throw new Error('start failed');
        }
        const { run_id: runId } = await response.json();
        activeRunId.value = runId;
        await fetchRun(runId);
    } catch {
        toast.error(trans('automations.test.error_starting'));
    } finally {
        isStarting.value = false;
    }
};

const runTest = async () => {
    if (isStarting.value) return;
    const proceed = await (props.beforeRun?.() ?? true);
    if (proceed === false) return;
    await start();
};

const statusLabel = (status: string): string => {
    const map: Record<string, string> = {
        running: trans('automations.test.in_progress'),
        completed: trans('automations.test.completed'),
        failed: trans('automations.test.failed'),
        waiting: trans('automations.test.waiting'),
    };
    return map[status] ?? status;
};

const nodeStatusIcon = (status: string) => {
    if (status === 'completed') return IconCircleCheck;
    if (status === 'failed') return IconAlertCircle;
    if (status === 'running') return IconLoader2;
    return IconCircleDot;
};

// Fetch nodes (RSS / HTTP) short-circuit via the `no_items` handle with an
// output of `{ fetch: { count: 0 } }` when nothing new arrived. Surface that
// as an explicit note instead of an uninformative empty JSON blob.
const isZeroFetchResult = (nodeRun: NodeRun): boolean => {
    if (nodeRun.status !== 'completed') return false;
    if (nodeRun.node_type !== 'fetch_rss' && nodeRun.node_type !== 'http_request') return false;
    const fetch = nodeRun.output?.fetch as { count?: number } | undefined;
    return fetch?.count === 0;
};
</script>

<template>
    <div class="flex flex-col gap-5 px-5 pt-5 pb-12">
        <div class="min-w-0">
            <span
                v-if="run?.is_dry_run"
                class="inline-flex -rotate-3 items-center rounded-md border-2 border-foreground bg-amber-200 px-2 py-0.5 text-[10px] font-black uppercase tracking-widest text-foreground shadow-[2px_2px_0_var(--foreground)]"
            >
                {{ $t('automations.test.dry_badge') }}
            </span>
            <p class="text-sm text-foreground/60" :class="{ 'mt-2': run?.is_dry_run }">
                {{ run?.is_dry_run === false ? $t('automations.test.real_data_hint') : $t('automations.test.description') }}
            </p>
        </div>

        <div class="flex items-center justify-between gap-3 rounded-xl border-2 border-foreground bg-card p-3 shadow-[3px_3px_0_var(--foreground)]">
            <label class="flex cursor-pointer items-center gap-2 text-sm font-semibold text-foreground/70">
                <Checkbox v-model="realData" :disabled="isStarting" />
                {{ $t('automations.test.with_real_data') }}
            </label>
            <Button size="sm" :disabled="isStarting" @click="runTest">
                <IconLoader2 v-if="isStarting" class="size-4 animate-spin" />
                <IconPlayerPlay v-else class="size-4" />
                {{ $t('automations.test.run') }}
            </Button>
        </div>

        <div
            v-if="run === null && !isStarting"
            class="rounded-xl border-2 border-dashed border-foreground/25 bg-card/40 p-8 text-center text-sm font-medium text-foreground/60"
        >
            {{ $t('automations.test.idle_hint') }}
        </div>

        <div v-if="run === null && isStarting" class="flex items-center gap-2.5 text-sm font-medium text-foreground/70">
            <IconLoader2 class="size-5 animate-spin" />
            {{ $t('automations.test.starting') }}
        </div>

        <div
            v-if="run && run.status === 'failed' && run.error?.message"
            class="flex items-start gap-2.5 rounded-xl border-2 border-rose-700 bg-rose-50 p-4 text-sm font-medium text-rose-800"
        >
            <IconAlertCircle class="mt-0.5 size-5 shrink-0" stroke-width="2.5" />
            <span>{{ run.error.message }}</span>
        </div>

        <div v-if="run && nodeRuns.length === 0" class="rounded-xl border-2 border-dashed border-foreground/25 bg-card/40 p-8 text-center text-sm font-medium text-foreground/60">
            <IconLoader2 class="mx-auto mb-2 size-6 animate-spin" />
            {{ $t('automations.test.no_node_runs') }}
        </div>

        <ul v-if="nodeRuns.length > 0" class="space-y-3">
            <li
                v-for="nodeRun in nodeRuns"
                :key="nodeRun.id"
                class="rounded-xl border-2 border-foreground bg-card shadow-[3px_3px_0_var(--foreground)]"
            >
                <div class="flex items-center gap-3 p-4">
                    <div
                        :class="[
                            'inline-flex size-10 -rotate-3 shrink-0 items-center justify-center rounded-xl border-2 border-foreground shadow-2xs',
                            nodeRun.status === 'completed' && 'bg-emerald-200 text-emerald-900',
                            nodeRun.status === 'failed' && 'bg-rose-200 text-rose-900',
                            nodeRun.status === 'running' && 'bg-amber-200 text-amber-900',
                            !['completed', 'failed', 'running'].includes(nodeRun.status) && 'bg-zinc-200 text-zinc-900',
                        ]"
                    >
                        <component :is="nodeStatusIcon(nodeRun.status)" :class="['size-5', nodeRun.status === 'running' && 'animate-spin']" stroke-width="2.5" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="text-base font-bold capitalize leading-tight">{{ nodeRun.node_type.replace('_', ' ') }}</p>
                        <p class="text-xs font-semibold uppercase tracking-wider text-foreground/50">{{ statusLabel(nodeRun.status) }}</p>
                    </div>
                </div>

                <div v-if="nodeRun.error || nodeRun.output" class="border-t-2 border-foreground/10 px-4 pb-4 pt-3">
                    <p v-if="nodeRun.error" class="rounded-lg border-2 border-rose-700 bg-rose-50 p-3 text-sm font-medium text-rose-800">
                        <span class="font-black uppercase text-xs tracking-wider">{{ $t('automations.test.node_error') }}:</span>
                        <span class="ml-1">{{ nodeRun.error.message }}</span>
                    </p>
                    <p
                        v-if="isZeroFetchResult(nodeRun)"
                        class="flex items-center gap-2 rounded-lg border-2 border-foreground/30 bg-card p-3 text-sm font-medium text-foreground/70"
                        :class="{ 'mt-3': nodeRun.error }"
                    >
                        <IconInfoCircle class="size-5 shrink-0 text-foreground/50" stroke-width="2.5" />
                        {{ $t('automations.test.no_new_items') }}
                    </p>
                    <details v-if="nodeRun.output && !isZeroFetchResult(nodeRun)" class="group" :class="{ 'mt-3': nodeRun.error }">
                        <summary class="flex cursor-pointer items-center gap-1.5 text-xs font-black uppercase tracking-wider text-foreground/60 hover:text-foreground">
                            <IconChevronRight class="size-4 transition-transform group-open:rotate-90" stroke-width="2.5" />
                            {{ $t('automations.test.node_output') }}
                        </summary>
                        <JsonViewer :value="nodeRun.output" class="mt-2" />
                    </details>
                </div>
            </li>
        </ul>
    </div>
</template>
