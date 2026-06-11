<script setup lang="ts">
import { indentWithTab } from '@codemirror/commands';
import { json } from '@codemirror/lang-json';
import { EditorState } from '@codemirror/state';
import { EditorView, keymap, placeholder as placeholderExt } from '@codemirror/view';
import { IconCopy } from '@tabler/icons-vue';
import { basicSetup } from 'codemirror';
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';

import debounce from '@/debounce';
import { copyToClipboard } from '@/lib/utils';

const props = withDefaults(
    defineProps<{
        modelValue: string;
        language?: 'json';
        readOnly?: boolean;
        placeholder?: string;
    }>(),
    {
        language: 'json',
        readOnly: false,
        placeholder: '',
    },
);

const emit = defineEmits<{
    'update:modelValue': [value: string];
}>();

const editorContainer = ref<HTMLElement>();
let view: EditorView | null = null;

const debouncedEmit = debounce((value: string) => {
    emit('update:modelValue', value);
}, 250);

const languageExtension = () => {
    switch (props.language) {
        case 'json':
        default:
            return json();
    }
};

const lightTheme = EditorView.theme({
    '&': {
        height: '100%',
        fontSize: '13px',
        color: 'var(--foreground)',
        backgroundColor: 'var(--card)',
        border: '2px solid var(--foreground)',
        borderRadius: 'var(--radius-md)',
        overflow: 'hidden',
    },
    '&.cm-focused': {
        outline: '2px solid var(--ring)',
        outlineOffset: '0px',
    },
    '.cm-scroller': {
        overflow: 'auto',
        fontFamily: 'var(--font-mono)',
        lineHeight: '1.6',
    },
    '.cm-content': {
        caretColor: 'var(--foreground)',
        padding: '8px 0',
    },
    '.cm-gutters': {
        backgroundColor: 'var(--muted)',
        color: 'var(--muted-foreground)',
        border: 'none',
        borderRight: '2px solid color-mix(in srgb, var(--foreground) 15%, transparent)',
    },
    '.cm-activeLine': {
        backgroundColor: 'color-mix(in srgb, var(--foreground) 4%, transparent)',
    },
    '.cm-activeLineGutter': {
        backgroundColor: 'color-mix(in srgb, var(--foreground) 8%, transparent)',
    },
    '.cm-selectionBackground, &.cm-focused .cm-selectionBackground, .cm-content ::selection':
        {
            backgroundColor: 'color-mix(in srgb, var(--ring) 20%, transparent)',
        },
    '.cm-cursor, .cm-dropCursor': {
        borderLeftColor: 'var(--foreground)',
    },
    '.cm-placeholder': {
        color: 'var(--muted-foreground)',
    },
});

onMounted(() => {
    if (!editorContainer.value) {
        return;
    }

    const updateListener = EditorView.updateListener.of((update) => {
        if (update.docChanged) {
            debouncedEmit(update.state.doc.toString());
        }
    });

    const extensions = [
        basicSetup,
        keymap.of([indentWithTab]),
        languageExtension(),
        EditorView.lineWrapping,
        lightTheme,
        updateListener,
    ];

    if (props.placeholder) {
        extensions.push(placeholderExt(props.placeholder));
    }

    if (props.readOnly) {
        extensions.push(EditorState.readOnly.of(true));
        extensions.push(EditorView.editable.of(false));
    }

    view = new EditorView({
        state: EditorState.create({
            doc: props.modelValue ?? '',
            extensions,
        }),
        parent: editorContainer.value,
    });
});

watch(
    () => props.modelValue,
    (value) => {
        if (!view) {
            return;
        }

        const current = view.state.doc.toString();

        if ((value ?? '') !== current) {
            view.dispatch({
                changes: {
                    from: 0,
                    to: current.length,
                    insert: value ?? '',
                },
            });
        }
    },
);

onBeforeUnmount(() => {
    debouncedEmit.cancel();
    view?.destroy();
    view = null;
});
</script>

<template>
    <div class="relative h-full w-full">
        <div ref="editorContainer" class="code-editor h-full w-full" />

        <button
            v-if="modelValue"
            type="button"
            class="absolute right-2 top-2 z-10 inline-flex size-7 items-center justify-center rounded-md border-2 border-foreground bg-card shadow-[1px_1px_0_var(--foreground)] transition hover:-translate-x-px hover:-translate-y-px hover:shadow-[2px_2px_0_var(--foreground)] active:translate-x-0 active:translate-y-0 active:shadow-[0_0_0_var(--foreground)]"
            :title="$t('common.actions.copy')"
            :aria-label="$t('common.actions.copy')"
            @click="copyToClipboard(modelValue)"
        >
            <IconCopy class="size-3.5" stroke-width="2.5" />
        </button>
    </div>
</template>
