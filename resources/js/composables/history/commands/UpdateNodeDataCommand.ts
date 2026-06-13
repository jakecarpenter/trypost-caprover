import type { Node } from '@vue-flow/core';
import type { Ref } from 'vue';

import type { Command } from '../types';

export class UpdateNodeDataCommand implements Command {
    readonly name = 'updateNodeData';

    constructor(
        private readonly nodeId: string,
        private readonly oldData: Record<string, unknown>,
        private readonly newData: Record<string, unknown>,
        private readonly nodesRef: Ref<Node[]>,
    ) {}

    revert(): void {
        this.nodesRef.value = this.nodesRef.value.map((n) =>
            n.id === this.nodeId ? { ...n, data: this.oldData } : n,
        );
    }

    getReverseCommand(): Command {
        return new UpdateNodeDataCommand(this.nodeId, this.newData, this.oldData, this.nodesRef);
    }
}
