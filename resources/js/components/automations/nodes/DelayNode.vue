<script setup lang="ts">
import { IconClock } from '@tabler/icons-vue';
import { Handle, Position } from '@vue-flow/core';
import { trans } from 'laravel-vue-i18n';
import { computed } from 'vue';

import type { DelayUnitValue } from '@/types/automation/delay-unit';

const props = defineProps<{
    data: {
        duration: number;
        unit: DelayUnitValue;
    };
    selected?: boolean;
}>();

const summary = computed(() => {
    const unit = trans(`automations.config.delay.units.${props.data.unit}`);
    return `${props.data.duration} ${unit}`;
});
</script>

<template>
    <div
        class="automation-node automation-node--accent-cyan"
        :class="{ 'is-selected': selected }"
    >
        <div class="automation-node__header">
            <div class="automation-node__icon-tile automation-node__icon-tile--cyan">
                <IconClock :size="16" />
            </div>
            <span class="automation-node__title">{{ $t('automations.nodes.delay') }}</span>
        </div>
        <div class="automation-node__summary">
            {{ summary }}
        </div>
        <Handle
            type="target"
            :position="Position.Left"
            class="!bg-cyan-500"
        />
        <Handle
            type="source"
            :position="Position.Right"
            class="!bg-cyan-500"
        />
    </div>
</template>
