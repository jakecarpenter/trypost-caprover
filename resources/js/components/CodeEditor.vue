<script setup lang="ts">
import { autocompletion } from '@codemirror/autocomplete';
import { indentWithTab } from '@codemirror/commands';
import { json } from '@codemirror/lang-json';
import { type Extension, EditorState } from '@codemirror/state';
import {
    Decoration,
    type DecorationSet,
    EditorView,
    keymap,
    MatchDecorator,
    placeholder as placeholderExt,
    ViewPlugin,
    type ViewUpdate,
} from '@codemirror/view';
import { IconArrowsMaximize, IconArrowsMinimize, IconCopy } from '@tabler/icons-vue';
import { basicSetup } from 'codemirror';
import { computed, inject, onBeforeUnmount, onMounted, type Ref, ref, watch } from 'vue';

import { Button } from '@/components/ui/button';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import {
    type ExpressionSuggestion,
    expressionCompletionSource,
    isKnownExpression,
} from '@/composables/useExpressionCompletions';
import debounce from '@/debounce';
import { copyToClipboard } from '@/lib/utils';

const props = withDefaults(
    defineProps<{
        modelValue: string;
        language?: 'json' | 'text';
        readOnly?: boolean;
        placeholder?: string;
        expandable?: boolean;
        label?: string;
    }>(),
    {
        language: 'json',
        readOnly: false,
        placeholder: '',
        expandable: false,
        label: '',
    },
);

const emit = defineEmits<{
    'update:modelValue': [value: string];
}>();

// Provided by the automation editor (Form.vue) for the currently selected node.
// Empty elsewhere, so the editor degrades to a plain field with no `{{ ... }}`
// suggestions.
const expressionCompletions = inject<Ref<ExpressionSuggestion[]>>(
    'automationExpressionCompletions',
    ref([]),
);

// Shared with Form.vue: toggling `.active` slides out the side panel (and shifts
// the sidebar to make room). Null outside the automation editor, so `expandable`
// only takes effect where Form provides the panel target.
const expandedPanel = inject<{ active: boolean } | null>('automationExpandedEditor', null);

const isExpanded = ref(false);
const canExpand = computed(() => props.expandable && expandedPanel !== null);

watch(isExpanded, (open) => {
    if (expandedPanel) {
        expandedPanel.active = open;
    }
});

const onKeydown = (event: KeyboardEvent): void => {
    if (event.key === 'Escape' && isExpanded.value) {
        isExpanded.value = false;
    }
};

// Paints `{{ ... }}` expressions as pills — primary-tinted when the path is
// resolvable for this node, amber/wavy when it isn't — so they read as
// variables and a wrong reference is obvious. Styles are inline on the mark so
// they win over the editor theme and JSON string-token colors.
const KNOWN_EXPR_STYLE =
    'background-color:color-mix(in srgb,var(--primary) 16%,transparent);color:var(--primary);border-radius:4px;padding:1px 3px;font-weight:600;';
const UNKNOWN_EXPR_STYLE =
    'background-color:color-mix(in srgb,#f59e0b 18%,transparent);color:#b45309;border-radius:4px;padding:1px 3px;font-weight:600;text-decoration:underline wavy #f59e0b;';

const expressionHighlighter = (() => {
    const matcher = new MatchDecorator({
        regexp: /\{\{\s*([\w.]+)\s*\}\}/g,
        decoration: (match) =>
            Decoration.mark({
                attributes: {
                    style: isKnownExpression(match[1], expressionCompletions.value)
                        ? KNOWN_EXPR_STYLE
                        : UNKNOWN_EXPR_STYLE,
                },
            }),
    });

    return ViewPlugin.fromClass(
        class {
            decorations: DecorationSet;

            constructor(view: EditorView) {
                this.decorations = matcher.createDeco(view);
            }

            update(update: ViewUpdate) {
                this.decorations = matcher.updateDeco(update, this.decorations);
            }
        },
        { decorations: (plugin) => plugin.decorations },
    );
})();

const editorContainer = ref<HTMLElement>();
let view: EditorView | null = null;

const debouncedEmit = debounce((value: string) => {
    emit('update:modelValue', value);
}, 250);

const languageExtension = (): Extension => {
    switch (props.language) {
        case 'text':
            return [];
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
        autocompletion({
            override: [expressionCompletionSource(() => expressionCompletions.value)],
            activateOnTyping: true,
            icons: false,
        }),
        expressionHighlighter,
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

    if (props.expandable) {
        window.addEventListener('keydown', onKeydown);
    }
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
    window.removeEventListener('keydown', onKeydown);
    // Switching nodes unmounts the editor; make sure the side panel collapses
    // instead of lingering empty.
    if (isExpanded.value && expandedPanel) {
        expandedPanel.active = false;
    }
});
</script>

<template>
    <div class="group relative h-full w-full">
        <div ref="editorContainer" class="code-editor h-full w-full" />

        <TooltipProvider :delay-duration="200">
            <div
                v-if="!isExpanded && (canExpand || modelValue)"
                class="absolute right-2 top-2 z-10 flex gap-0.5 rounded-lg border-2 border-foreground bg-card p-0.5 opacity-0 shadow-[1px_1px_0_var(--foreground)] transition-opacity duration-150 group-hover:opacity-100 focus-within:opacity-100"
            >
                <Tooltip v-if="canExpand">
                    <TooltipTrigger as-child>
                        <button
                            type="button"
                            class="inline-flex size-6 items-center justify-center rounded-md text-foreground/70 transition-colors hover:bg-foreground/10 hover:text-foreground"
                            :aria-label="$t('automations.config.expand_editor')"
                            @click="isExpanded = true"
                        >
                            <IconArrowsMaximize class="size-3.5" stroke-width="2.5" />
                        </button>
                    </TooltipTrigger>
                    <TooltipContent>{{ $t('automations.config.expand_editor') }}</TooltipContent>
                </Tooltip>

                <Tooltip v-if="modelValue">
                    <TooltipTrigger as-child>
                        <button
                            type="button"
                            class="inline-flex size-6 items-center justify-center rounded-md text-foreground/70 transition-colors hover:bg-foreground/10 hover:text-foreground"
                            :aria-label="$t('common.actions.copy')"
                            @click="copyToClipboard(modelValue)"
                        >
                            <IconCopy class="size-3.5" stroke-width="2.5" />
                        </button>
                    </TooltipTrigger>
                    <TooltipContent>{{ $t('common.actions.copy') }}</TooltipContent>
                </Tooltip>
            </div>
        </TooltipProvider>

        <Teleport v-if="canExpand && isExpanded" to="#automation-expanded-editor">
            <div class="flex shrink-0 items-center justify-between gap-2 border-b-2 border-foreground/10 px-3 py-3">
                <span class="truncate text-sm font-bold">{{ label || $t('automations.config.expand_editor') }}</span>
                <TooltipProvider :delay-duration="200">
                    <Tooltip>
                        <TooltipTrigger as-child>
                            <Button variant="ghost" size="icon-sm" :aria-label="$t('automations.config.minimize_editor')" @click="isExpanded = false">
                                <IconArrowsMinimize class="size-4" />
                            </Button>
                        </TooltipTrigger>
                        <TooltipContent>{{ $t('automations.config.minimize_editor') }}</TooltipContent>
                    </Tooltip>
                </TooltipProvider>
            </div>
            <div class="min-h-0 flex-1 p-4">
                <CodeEditor
                    :model-value="modelValue"
                    :language="language"
                    :placeholder="placeholder"
                    :read-only="readOnly"
                    @update:model-value="emit('update:modelValue', $event)"
                />
            </div>
        </Teleport>
    </div>
</template>
