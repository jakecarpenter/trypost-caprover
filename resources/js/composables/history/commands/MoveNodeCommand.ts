import type { Node, XYPosition } from '@vue-flow/core';
import type { Ref } from 'vue';

import type { Command } from '../types';

export class MoveNodeCommand implements Command {
    readonly name = 'moveNode';

    constructor(
        private readonly nodeId: string,
        private readonly oldPosition: XYPosition,
        private readonly newPosition: XYPosition,
        private readonly nodesRef: Ref<Node[]>,
    ) {}

    revert(): void {
        this.nodesRef.value = this.nodesRef.value.map((n) =>
            n.id === this.nodeId ? { ...n, position: { ...this.oldPosition } } : n,
        );
    }

    getReverseCommand(): Command {
        return new MoveNodeCommand(this.nodeId, this.newPosition, this.oldPosition, this.nodesRef);
    }
}
