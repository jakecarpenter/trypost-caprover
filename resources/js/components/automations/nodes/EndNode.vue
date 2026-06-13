<script setup lang="ts">
import { IconCircleX } from '@tabler/icons-vue';
import { Handle, Position } from '@vue-flow/core';
import { computed } from 'vue';

const props = defineProps<{
    data: {
        reason?: string;
    };
    selected?: boolean;
}>();

const summary = computed(() => props.data.reason || null);
</script>

<template>
    <div
        class="automation-node automation-node--accent-zinc"
        :class="{ 'is-selected': selected }"
    >
        <div class="automation-node__header">
            <div class="automation-node__icon-tile automation-node__icon-tile--zinc">
                <IconCircleX :size="16" />
            </div>
            <span class="automation-node__title">{{ $t('automations.nodes.end') }}</span>
        </div>
        <div class="automation-node__summary">
            {{ summary ?? $t('automations.nodes.end_summary') }}
        </div>
        <Handle
            type="target"
            :position="Position.Left"
            class="!bg-zinc-500"
        />
    </div>
</template>
