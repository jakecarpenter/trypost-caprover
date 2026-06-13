<script setup lang="ts">
import { IconGitBranch } from '@tabler/icons-vue';
import { Handle, Position } from '@vue-flow/core';
import { trans } from 'laravel-vue-i18n';
import { computed } from 'vue';

import { ConditionHandle } from '@/types/automation/condition-handle';
import type { ConditionOperatorValue } from '@/types/automation/condition-operator';

const props = defineProps<{
    data: {
        field?: string;
        operator: ConditionOperatorValue;
        value?: string;
    };
    selected?: boolean;
}>();

const summary = computed(() => {
    const field = props.data.field || '…';
    const operator = trans(`automations.config.condition.operators.${props.data.operator}`);
    const value = props.data.value || '…';
    return `${field} ${operator} ${value}`;
});
</script>

<template>
    <div
        class="automation-node automation-node--wide automation-node--accent-rose"
        :class="{ 'is-selected': selected }"
    >
        <div class="automation-node__header">
            <div class="automation-node__icon-tile automation-node__icon-tile--rose">
                <IconGitBranch :size="16" />
            </div>
            <span class="automation-node__title">{{ $t('automations.nodes.condition') }}</span>
        </div>
        <div class="automation-node__summary" :title="summary">
            {{ summary }}
        </div>

        <Handle
            type="target"
            :position="Position.Left"
            class="!bg-rose-500"
        />
        <Handle
            :id="ConditionHandle.Yes"
            type="source"
            :position="Position.Right"
            class="!bg-emerald-500"
            :style="{ top: '35%' }"
        />
        <span class="pointer-events-none absolute left-full top-[35%] z-10 ml-3 -translate-y-1/2 rounded bg-background px-1.5 text-[10px] font-bold uppercase tracking-wider text-emerald-700">yes</span>
        <Handle
            :id="ConditionHandle.No"
            type="source"
            :position="Position.Right"
            class="!bg-rose-500"
            :style="{ top: '75%' }"
        />
        <span class="pointer-events-none absolute left-full top-[75%] z-10 ml-3 -translate-y-1/2 rounded bg-background px-1.5 text-[10px] font-bold uppercase tracking-wider text-rose-700">no</span>
    </div>
</template>
