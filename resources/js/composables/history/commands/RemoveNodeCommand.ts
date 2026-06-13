import type { Node } from '@vue-flow/core';
import type { Ref } from 'vue';

import type { Command } from '../types';
import { AddNodeCommand } from './AddNodeCommand';

export class RemoveNodeCommand implements Command {
    readonly name = 'removeNode';

    constructor(
        private readonly node: Node,
        private readonly nodesRef: Ref<Node[]>,
    ) {}

    revert(): void {
        this.nodesRef.value = [...this.nodesRef.value, this.node];
    }

    getReverseCommand(): Command {
        return new AddNodeCommand(this.node, this.nodesRef);
    }
}
