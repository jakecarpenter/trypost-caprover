<script setup lang="ts">
import { IconWebhook } from '@tabler/icons-vue';
import { Handle, Position } from '@vue-flow/core';
import { computed } from 'vue';

import type { HttpMethodValue } from '@/types/automation/http-method';

const props = defineProps<{
    data: {
        url?: string;
        method: HttpMethodValue;
    };
    selected?: boolean;
}>();

const summary = computed(() => {
    const method = props.data.method.toUpperCase();
    const url = props.data.url || 'https://…';
    return `${method} · ${url}`;
});
</script>

<template>
    <div
        class="automation-node automation-node--wide automation-node--accent-slate"
        :class="{ 'is-selected': selected }"
    >
        <div class="automation-node__header">
            <div class="automation-node__icon-tile automation-node__icon-tile--slate">
                <IconWebhook :size="16" />
            </div>
            <span class="automation-node__title">{{ $t('automations.nodes.webhook') }}</span>
        </div>
        <div class="automation-node__summary" :title="summary">
            {{ summary }}
        </div>
        <Handle
            type="target"
            :position="Position.Left"
            class="!bg-slate-500"
        />
        <Handle
            type="source"
            :position="Position.Right"
            class="!bg-slate-500"
        />
    </div>
</template>
