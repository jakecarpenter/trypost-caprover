<script setup lang="ts">
import { IconRss } from '@tabler/icons-vue';
import { Handle, Position } from '@vue-flow/core';
import { computed } from 'vue';

const props = defineProps<{
    data: {
        feed_url?: string;
    };
    selected?: boolean;
}>();

const summary = computed(() => {
    const url = props.data.feed_url;
    if (!url) return '—';
    try {
        return new URL(url).hostname;
    } catch {
        return url;
    }
});
</script>

<template>
    <div
        class="automation-node automation-node--accent-amber"
        :class="{ 'is-selected': selected }"
    >
        <div class="automation-node__header">
            <div class="automation-node__icon-tile automation-node__icon-tile--amber">
                <IconRss :size="16" />
            </div>
            <span class="automation-node__title">{{ $t('automations.nodes.fetch_rss') }}</span>
        </div>
        <div class="automation-node__summary">
            {{ summary }}
        </div>
        <Handle type="target" :position="Position.Left" class="!bg-amber-500" />
        <Handle
            id="default"
            type="source"
            :position="Position.Right"
            class="!bg-emerald-500"
            :style="{ top: '35%' }"
        />
        <span class="pointer-events-none absolute left-full top-[35%] z-10 ml-3 -translate-y-1/2 whitespace-nowrap rounded bg-background px-1.5 text-[10px] font-bold uppercase tracking-wider text-emerald-700">{{ $t('automations.nodes.handles.items') }}</span>
        <Handle
            id="no_items"
            type="source"
            :position="Position.Right"
            class="!bg-rose-500"
            :style="{ top: '75%' }"
        />
        <span class="pointer-events-none absolute left-full top-[75%] z-10 ml-3 -translate-y-1/2 whitespace-nowrap rounded bg-background px-1.5 text-[10px] font-bold uppercase tracking-wider text-rose-700">{{ $t('automations.nodes.handles.no_items') }}</span>
    </div>
</template>
