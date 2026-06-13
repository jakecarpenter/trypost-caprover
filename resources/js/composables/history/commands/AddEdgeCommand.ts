import type { Edge } from '@vue-flow/core';
import type { Ref } from 'vue';

import type { Command } from '../types';
import { RemoveEdgeCommand } from './RemoveEdgeCommand';

export class AddEdgeCommand implements Command {
    readonly name = 'addEdge';

    constructor(
        private readonly edge: Edge,
        private readonly edgesRef: Ref<Edge[]>,
    ) {}

    revert(): void {
        this.edgesRef.value = this.edgesRef.value.filter((e) => e.id !== this.edge.id);
    }

    getReverseCommand(): Command {
        return new RemoveEdgeCommand(this.edge, this.edgesRef);
    }
}
