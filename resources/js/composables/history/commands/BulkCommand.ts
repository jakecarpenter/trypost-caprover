import type { Command } from '../types';

/**
 * Groups several commands into a single undo step. Useful for actions that
 * touch multiple state shards at once (e.g. deleting a node also removes the
 * edges connected to it — both removals should undo together).
 */
export class BulkCommand implements Command {
    readonly name = 'bulk';

    constructor(public readonly commands: Command[]) {}

    revert(): void {
        // Revert from newest to oldest so the inverse mirrors the original order.
        for (let i = this.commands.length - 1; i >= 0; i--) {
            this.commands[i].revert();
        }
    }

    getReverseCommand(): Command {
        const reverses = [...this.commands].reverse().map((c) => c.getReverseCommand());
        return new BulkCommand(reverses);
    }
}
