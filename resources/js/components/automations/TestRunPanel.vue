<script setup lang="ts">
import { useHttp } from '@inertiajs/vue3';
import { IconAlertCircle, IconChevronRight, IconCircleCheck, IconCircleDot, IconInfoCircle, IconLoader2, IconPlayerPlay } from '@tabler/icons-vue';
import { trans } from 'laravel-vue-i18n';
import { ref } from 'vue';
import { toast } from 'vue-sonner';

import { showRun as showRunRoute } from '@/actions/App/Http/Controllers/App/AutomationController';
import JsonViewer from '@/components/JsonViewer.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { useAutomationEcho } from '@/composables/echo/useAutomationEcho';
import { test as testAutomation } from '@/routes/app/automations';
import { NodeRunStatus, type NodeRunStatusValue } from '@/types/automation/node-run-status';
import { NodeType, type NodeTypeValue } from '@/types/automation/node-type';
import { RunStatus, type RunStatusValue } from '@/types/automation/run-status';

interface NodeRun {
    id: string;
    node_id: string;
    node_type: NodeTypeValue;
    status: NodeRunStatusValue;
    input: Record<string, unknown> | null;
    output: Record<string, unknown> | null;
    error: { message?: string } | null;
    started_at: string | null;
    finished_at: string | null;
}

interface Run {
    id: string;
    status: RunStatusValue;
    context: Record<string, unknown> | null;
    error: { message?: string } | null;
    started_at: string | null;
    finished_at: string | null;
    is_dry_run: boolean;
}

const props = defineProps<{ automationId: string; beforeRun?: () => Promise<boolean> | boolean; configIssue?: string | null }>();

const isStarting = ref(false);
const realData = ref(false);
const run = ref<Run | null>(null);
const nodeRuns = ref<NodeRun[]>([]);
const activeRunId = ref<string | null>(null);

const testHttp = useHttp<{ with_real_data: boolean }, { run_id: string }>({ with_real_data: false });
const runHttp = useHttp<Record<string, never>, { run: Run; node_runs: NodeRun[] }>({});

const fetchRun = async (runId: string): Promise<void> => {
    try {
        const data = await runHttp.get(showRunRoute.url({ automation: props.automationId, run: runId }));
        run.value = data.run;
        nodeRuns.value = data.node_runs;
    } catch {
        // Silent on refresh failures — the next broadcast will retry.
    }
};

// Subscribe once to the automation's private channel. The backend broadcasts
// a tiny `{ run_id, status }` payload on every run/node update; we refetch the
// full state only when the event is for our active run. Zero polling.
useAutomationEcho<{ run_id: string; root_run_id: string; status: string }>(
    props.automationId,
    '.automation.run.updated',
    (payload) => {
        // Refetch when any branch of the active test family advances — a fan-out
        // forks sibling runs whose root_run_id points back at the run we started.
        if (payload.root_run_id === activeRunId.value) {
            fetchRun(activeRunId.value);
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
        testHttp.with_real_data = realData.value;
        const { run_id: runId } = await testHttp.post(testAutomation.url(props.automationId));
        activeRunId.value = runId;
        await fetchRun(runId);
    } catch (error: unknown) {
        const message = (error as { response?: { data?: { message?: string } } })?.response?.data?.message;
        toast.error(message ?? trans('automations.test.error_starting'));
    } finally {
        isStarting.value = false;
    }
};

const runTest = async () => {
    if (isStarting.value || props.configIssue) return;
    const proceed = await (props.beforeRun?.() ?? true);
    if (proceed === false) return;
    await start();
};

const statusLabel = (status: NodeRunStatusValue): string => {
    const map: Partial<Record<NodeRunStatusValue, string>> = {
        [NodeRunStatus.Running]: trans('automations.test.in_progress'),
        [NodeRunStatus.Completed]: trans('automations.test.completed'),
        [NodeRunStatus.Failed]: trans('automations.test.failed'),
    };
    return map[status] ?? status;
};

const nodeStatusIcon = (status: NodeRunStatusValue) => {
    if (status === NodeRunStatus.Completed) return IconCircleCheck;
    if (status === NodeRunStatus.Failed) return IconAlertCircle;
    if (status === NodeRunStatus.Running) return IconLoader2;
    return IconCircleDot;
};

const nodeStatusTile = (status: NodeRunStatusValue): string => {
    if (status === NodeRunStatus.Completed) return 'bg-emerald-200 text-emerald-900';
    if (status === NodeRunStatus.Failed) return 'bg-rose-200 text-rose-900';
    if (status === NodeRunStatus.Running) return 'bg-amber-200 text-amber-900';
    return 'bg-zinc-200 text-zinc-900';
};

// Fetch nodes (RSS / HTTP) short-circuit via the `no_items` handle with an
// output of `{ fetch: { count: 0 } }` when nothing new arrived. Surface that
// as an explicit note instead of an uninformative empty JSON blob.
const isZeroFetchResult = (nodeRun: NodeRun): boolean => {
    if (nodeRun.status !== NodeRunStatus.Completed) return false;
    if (nodeRun.node_type !== NodeType.FetchRss && nodeRun.node_type !== NodeType.HttpRequest) return false;
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
            <Label class="cursor-pointer font-semibold text-foreground/70">
                <Checkbox v-model="realData" :disabled="isStarting" />
                {{ $t('automations.test.with_real_data') }}
            </Label>
            <Button size="sm" :disabled="isStarting || !!configIssue" @click="runTest">
                <IconLoader2 v-if="isStarting" class="size-4 animate-spin" />
                <IconPlayerPlay v-else class="size-4" />
                {{ $t('automations.test.run') }}
            </Button>
        </div>

        <p v-if="configIssue" class="flex items-center gap-1.5 text-xs font-medium text-amber-600 dark:text-amber-500">
            <IconAlertCircle class="size-4 flex-shrink-0" />
            {{ configIssue }}
        </p>

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
            v-if="run && run.status === RunStatus.Failed && run.error?.message"
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
                        :class="['inline-flex size-10 -rotate-3 shrink-0 items-center justify-center rounded-xl border-2 border-foreground shadow-2xs', nodeStatusTile(nodeRun.status)]"
                    >
                        <component :is="nodeStatusIcon(nodeRun.status)" :class="['size-5', nodeRun.status === NodeRunStatus.Running && 'animate-spin']" stroke-width="2.5" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="text-base font-bold capitalize leading-tight">{{ $t(`automations.node_type.${nodeRun.node_type}`) }}</p>
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
