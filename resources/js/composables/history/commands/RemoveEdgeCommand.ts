import type { Edge } from '@vue-flow/core';
import type { Ref } from 'vue';

import type { Command } from '../types';
import { AddEdgeCommand } from './AddEdgeCommand';

export class RemoveEdgeCommand implements Command {
    readonly name = 'removeEdge';

    constructor(
        private readonly edge: Edge,
        private readonly edgesRef: Ref<Edge[]>,
    ) {}

    revert(): void {
        this.edgesRef.value = [...this.edgesRef.value, this.edge];
    }

    getReverseCommand(): Command {
        return new AddEdgeCommand(this.edge, this.edgesRef);
    }
}
