import { computed, shallowRef, type ComputedRef } from 'vue';

import { BulkCommand } from './commands/BulkCommand';
import type { Command } from './types';

const STACK_LIMIT = 100;

export interface UseHistoryReturn {
    /** True while the composable is reverting/applying a command (use to skip auto-push). */
    isReverting: () => boolean;
    canUndo: ComputedRef<boolean>;
    canRedo: ComputedRef<boolean>;
    /**
     * Add a command to the undo stack. If a bulk recording is open, the command
     * is appended to the bulk instead of becoming its own undo step.
     */
    push: (command: Command, options?: { clearRedo?: boolean }) => void;
    undo: () => boolean;
    redo: () => boolean;
    /** Begin grouping subsequent `push` calls into a single undo step. */
    startBulk: () => void;
    /** Close the current bulk recording and add it to the stack (if non-empty). */
    endBulk: () => void;
    clear: () => void;
}

export const useHistory = (): UseHistoryReturn => {
    const undoStack = shallowRef<Command[]>([]);
    const redoStack = shallowRef<Command[]>([]);
    let reverting = false;
    let bulk: Command[] | null = null;

    const trim = (stack: Command[]): Command[] =>
        stack.length > STACK_LIMIT ? stack.slice(stack.length - STACK_LIMIT) : stack;

    const push = (command: Command, options: { clearRedo?: boolean } = {}): void => {
        if (reverting) return;

        if (bulk !== null) {
            bulk.push(command);
            return;
        }

        const { clearRedo = true } = options;
        undoStack.value = trim([...undoStack.value, command]);
        if (clearRedo && redoStack.value.length > 0) {
            redoStack.value = [];
        }
    };

    const apply = (
        sourceStack: typeof undoStack,
        sinkStack: typeof undoStack,
    ): boolean => {
        const stack = sourceStack.value;
        if (stack.length === 0) return false;

        const command = stack[stack.length - 1];
        sourceStack.value = stack.slice(0, -1);

        reverting = true;
        try {
            command.revert();
        } finally {
            reverting = false;
        }

        sinkStack.value = trim([...sinkStack.value, command.getReverseCommand()]);
        return true;
    };

    const undo = (): boolean => apply(undoStack, redoStack);
    const redo = (): boolean => apply(redoStack, undoStack);

    const startBulk = (): void => {
        bulk = [];
    };

    const endBulk = (): void => {
        if (bulk && bulk.length > 0) {
            const commands = bulk;
            bulk = null;
            push(commands.length === 1 ? commands[0] : new BulkCommand(commands));
            return;
        }
        bulk = null;
    };

    const clear = (): void => {
        undoStack.value = [];
        redoStack.value = [];
    };

    return {
        isReverting: () => reverting,
        canUndo: computed(() => undoStack.value.length > 0),
        canRedo: computed(() => redoStack.value.length > 0),
        push,
        undo,
        redo,
        startBulk,
        endBulk,
        clear,
    };
};
