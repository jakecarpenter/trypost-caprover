<script setup lang="ts">
import { IconSend } from '@tabler/icons-vue';
import { Handle, Position } from '@vue-flow/core';
import { trans } from 'laravel-vue-i18n';
import { computed } from 'vue';

import { PublishMode, type PublishModeValue } from '@/types/automation/publish-mode';

const props = defineProps<{
    data: {
        mode: PublishModeValue;
        scheduled_offset?: number;
    };
    selected?: boolean;
}>();

const summary = computed(() => {
    const mode = trans(`automations.config.publish.modes.${props.data.mode}`);
    if (props.data.mode === PublishMode.Scheduled && props.data.scheduled_offset != null) {
        return trans('automations.config.publish.offset_summary', { mode, offset: String(props.data.scheduled_offset) });
    }
    return mode;
});
</script>

<template>
    <div
        class="automation-node automation-node--accent-emerald"
        :class="{ 'is-selected': selected }"
    >
        <div class="automation-node__header">
            <div class="automation-node__icon-tile automation-node__icon-tile--emerald">
                <IconSend :size="16" />
            </div>
            <span class="automation-node__title">{{ $t('automations.nodes.publish') }}</span>
        </div>
        <div class="automation-node__summary">
            {{ summary }}
        </div>
        <Handle
            type="target"
            :position="Position.Left"
            class="!bg-emerald-500"
        />
        <Handle
            type="source"
            :position="Position.Right"
            class="!bg-emerald-500"
        />
    </div>
</template>
