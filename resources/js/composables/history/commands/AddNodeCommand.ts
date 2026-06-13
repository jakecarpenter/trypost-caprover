import type { Node } from '@vue-flow/core';
import type { Ref } from 'vue';

import type { Command } from '../types';
import { RemoveNodeCommand } from './RemoveNodeCommand';

export class AddNodeCommand implements Command {
    readonly name = 'addNode';

    constructor(
        private readonly node: Node,
        private readonly nodesRef: Ref<Node[]>,
    ) {}

    revert(): void {
        this.nodesRef.value = this.nodesRef.value.filter((n) => n.id !== this.node.id);
    }

    getReverseCommand(): Command {
        return new RemoveNodeCommand(this.node, this.nodesRef);
    }
}
