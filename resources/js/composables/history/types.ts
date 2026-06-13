/**
 * A reversible action. Implementations encapsulate enough state to undo
 * themselves (`revert`) and to produce the inverse operation for redo
 * (`getReverseCommand`).
 */
export interface Command {
    /** Human-readable identifier, used only for debugging. */
    readonly name: string;
    revert(): void;
    getReverseCommand(): Command;
}
