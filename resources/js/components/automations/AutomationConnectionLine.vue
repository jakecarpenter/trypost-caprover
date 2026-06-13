<script setup lang="ts">
import { getSmoothStepPath, Position } from '@vue-flow/core';
import { computed } from 'vue';

const props = defineProps<{
    sourceX: number;
    sourceY: number;
    sourcePosition?: Position;
    targetX: number;
    targetY: number;
    targetPosition?: Position;
}>();

const pathData = computed(() => {
    const [path] = getSmoothStepPath({
        sourceX: props.sourceX,
        sourceY: props.sourceY,
        sourcePosition: props.sourcePosition ?? Position.Right,
        targetX: props.targetX,
        targetY: props.targetY,
        targetPosition: props.targetPosition ?? Position.Left,
        borderRadius: 32,
        offset: 24,
    });
    return path;
});
</script>

<template>
    <g>
        <defs>
            <marker
                id="automation-connection-arrow"
                viewBox="-10 -10 20 20"
                refX="0"
                refY="0"
                markerWidth="9"
                markerHeight="9"
                markerUnits="strokeWidth"
                orient="auto-start-reverse"
            >
                <polyline
                    points="-5,-4 0,0 -5,4 -5,-4"
                    style="stroke: #0a0a0a; fill: #0a0a0a; stroke-width: 1; stroke-linecap: round; stroke-linejoin: round;"
                />
            </marker>
        </defs>
        <path
            class="vue-flow__connection-path"
            :d="pathData"
            marker-end="url(#automation-connection-arrow)"
        />
    </g>
</template>
