<script setup lang="ts">
import { IconCopy } from '@tabler/icons-vue';
import hljs from 'highlight.js/lib/core';
import jsonLang from 'highlight.js/lib/languages/json';
import { computed } from 'vue';

import { copyToClipboard } from '@/lib/utils';

hljs.registerLanguage('json', jsonLang);

const props = defineProps<{ value: unknown }>();

const serialized = computed(() => {
    if (props.value === null || props.value === undefined) return '';
    try {
        return JSON.stringify(props.value, null, 2);
    } catch {
        return String(props.value);
    }
});

const highlighted = computed(() => {
    if (serialized.value === '') return '';
    return hljs.highlight(serialized.value, { language: 'json' }).value;
});
</script>

<template>
    <div class="json-viewer overflow-hidden rounded-lg border-2 border-foreground">
        <div class="flex items-center justify-end border-b-2 border-foreground/15 bg-card px-2 py-1.5">
            <button
                type="button"
                class="inline-flex h-7 items-center gap-1.5 rounded-md border-2 border-foreground bg-card px-2 text-xs font-bold uppercase tracking-wider shadow-[1px_1px_0_var(--foreground)] transition hover:-translate-x-px hover:-translate-y-px hover:shadow-[2px_2px_0_var(--foreground)] active:translate-x-0 active:translate-y-0 active:shadow-[0_0_0_var(--foreground)]"
                @click="copyToClipboard(serialized)"
            >
                <IconCopy class="size-3.5" stroke-width="2.5" />
                {{ $t('common.actions.copy') }}
            </button>
        </div>
        <pre class="json-viewer__body overflow-x-auto p-3 text-xs leading-relaxed"><code class="hljs language-json" v-html="highlighted" /></pre>
    </div>
</template>
