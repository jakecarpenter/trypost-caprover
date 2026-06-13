import { onKeyStroke } from '@vueuse/core';

type ShortcutCallback = (event: KeyboardEvent) => void;

interface ShortcutOptions {
    /**
     * Skip the callback when the event originates from an editable element
     * (input, textarea, select, or contenteditable). Useful for bare keys like
     * `backspace` or `escape` that should only act on the canvas/page level.
     */
    ignoreOnInput?: boolean;
}

const MOD_NAMES = new Set(['mod', 'cmd', 'ctrl', 'control', 'meta']);
const SHIFT_NAMES = new Set(['shift']);
const ALT_NAMES = new Set(['alt', 'option']);

const isEditableTarget = (target: EventTarget | null): boolean => {
    if (!(target instanceof HTMLElement)) return false;
    if (target.isContentEditable) return true;
    const tag = target.tagName;
    return tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT';
};

/**
 * Register a keyboard shortcut scoped to the current component lifecycle.
 *
 * Combo syntax: `[modifier+]…key`, joined by `+`.
 *  - `mod` matches `cmd` on macOS and `ctrl` everywhere else (cross-platform).
 *  - Supported modifiers: `mod`, `cmd`, `ctrl`, `shift`, `alt` / `option`.
 *  - The trailing token is the key, case-insensitive (`'s'`, `'escape'`, `'arrowdown'`).
 *
 * Examples:
 *   useShortcut('mod+s', () => save());
 *   useShortcut('mod+shift+z', () => redo());
 *   useShortcut('backspace', () => deleteSelected(), { ignoreOnInput: true });
 */
export const useShortcut = (combo: string, callback: ShortcutCallback, options: ShortcutOptions = {}): void => {
    const parts = combo.toLowerCase().split('+').map((p) => p.trim());
    const key = parts.at(-1) ?? '';
    const modifiers = parts.slice(0, -1);

    const wantsMod = modifiers.some((m) => MOD_NAMES.has(m));
    const wantsShift = modifiers.some((m) => SHIFT_NAMES.has(m));
    const wantsAlt = modifiers.some((m) => ALT_NAMES.has(m));

    onKeyStroke(
        (event) => event.key.toLowerCase() === key,
        (event) => {
            const hasMod = event.metaKey || event.ctrlKey;
            if (wantsMod !== hasMod) return;
            if (wantsShift !== event.shiftKey) return;
            if (wantsAlt !== event.altKey) return;
            if (options.ignoreOnInput && isEditableTarget(event.target)) return;
            event.preventDefault();
            callback(event);
        },
    );
};
