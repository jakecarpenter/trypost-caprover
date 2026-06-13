import { computed, inject } from 'vue';

/**
 * Reactive flag, provided by the automation editor (Form.vue), that is true
 * while an expandable CodeEditor is open in the side panel. Config panels use it
 * to collapse the inline field — the editing happens in the panel instead.
 */
export const useExpandedEditor = () => {
    const state = inject<{ active: boolean } | null>('automationExpandedEditor', null);

    return computed(() => state?.active ?? false);
};
