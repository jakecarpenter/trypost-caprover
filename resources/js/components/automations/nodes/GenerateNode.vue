<script setup lang="ts">
import { IconSparkles } from '@tabler/icons-vue';
import { Handle, Position } from '@vue-flow/core';
import { trans, transChoice } from 'laravel-vue-i18n';
import { computed } from 'vue';

const props = defineProps<{
    data: {
        accounts?: Array<{ social_account_id: string }>;
        social_account_ids?: string[];
        format?: string;
    };
    selected?: boolean;
}>();

const summary = computed(() => {
    const count = props.data.accounts?.length ?? props.data.social_account_ids?.length ?? 0;
    const format = trans(`automations.config.generate.formats.${props.data.format ?? 'single'}`);
    return transChoice('automations.config.generate.account_summary', count, { count: String(count), format });
});
</script>

<template>
    <div
        class="automation-node automation-node--accent-blue"
        :class="{ 'is-selected': selected }"
    >
        <div class="automation-node__header">
            <div class="automation-node__icon-tile automation-node__icon-tile--blue">
                <IconSparkles :size="16" />
            </div>
            <span class="automation-node__title">{{ $t('automations.nodes.generate') }}</span>
        </div>
        <div class="automation-node__summary">
            {{ summary }}
        </div>
        <Handle
            type="target"
            :position="Position.Left"
            class="!bg-blue-500"
        />
        <Handle
            type="source"
            :position="Position.Right"
            class="!bg-blue-500"
        />
    </div>
</template>
