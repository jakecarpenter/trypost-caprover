<script setup lang="ts">
import { IconBolt } from '@tabler/icons-vue';
import { Handle, Position } from '@vue-flow/core';
import { computed } from 'vue';

import { triggerSummary } from '@/components/automations/schedule-summary';
import type { ScheduleData } from '@/types/automation/schedule-data';

const props = defineProps<{
    data: ScheduleData;
    selected?: boolean;
}>();

const summary = computed(() => triggerSummary(props.data));
</script>

<template>
    <div
        class="automation-node automation-node--accent-violet"
        :class="{ 'is-selected': selected }"
    >
        <div class="automation-node__header">
            <div class="automation-node__icon-tile automation-node__icon-tile--violet">
                <IconBolt :size="16" />
            </div>
            <span class="automation-node__title">{{ $t('automations.nodes.trigger') }}</span>
        </div>
        <div class="automation-node__summary">
            {{ summary }}
        </div>
        <Handle
            type="source"
            :position="Position.Right"
            class="!bg-violet-500"
        />
    </div>
</template>
