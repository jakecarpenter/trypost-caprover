import type { Completion, CompletionContext, CompletionResult } from '@codemirror/autocomplete';
import { trans } from 'laravel-vue-i18n';

import type { AutomationVariable } from '@/types/automation/automation';

export interface ExpressionSuggestion {
    label: string;
    info: string;
}

interface GraphNode {
    id: string;
    type?: string;
    data?: Record<string, unknown>;
}

interface GraphEdge {
    source: string;
    target: string;
}

const suggestion = (label: string, key: string): ExpressionSuggestion => ({
    label,
    info: trans(`automations.expr.${key}`),
});

/**
 * Expressions a node makes available to everything downstream — mirrors the
 * `output` each node action merges into the run context on the backend.
 */
const providedBy = (node: GraphNode): ExpressionSuggestion[] => {
    switch (node.type) {
        case 'trigger': {
            const triggerType = node.data?.trigger_type;
            if (triggerType === 'post_published' || triggerType === 'post_scheduled') {
                return [
                    suggestion('trigger.post.id', 'trigger_post_id'),
                    suggestion('trigger.post.content', 'trigger_post_content'),
                    suggestion('trigger.post.status', 'trigger_post_status'),
                    suggestion('trigger.post.scheduled_at', 'trigger_post_scheduled_at'),
                    suggestion('trigger.post.published_at', 'trigger_post_published_at'),
                ];
            }
            return [suggestion('trigger.event', 'trigger_event'), suggestion('trigger.fired_at', 'trigger_fired_at')];
        }
        case 'fetch_rss':
            return [
                suggestion('fetched.title', 'fetched_title'),
                suggestion('fetched.link', 'fetched_link'),
                suggestion('fetched.description', 'fetched_description'),
                suggestion('fetched.pubDate', 'fetched_pubdate'),
            ];
        case 'http_request':
            return [suggestion('fetched', 'fetched_http')];
        case 'generate':
            return [
                suggestion('generated.content', 'generated_content'),
                suggestion('generated.post_url', 'generated_post_url'),
            ];
        default:
            return [];
    }
};

/**
 * Every node upstream of `nodeId`, following connections backwards. These are
 * the only nodes whose output is in scope when the given node runs.
 */
const ancestorNodes = (nodeId: string, nodes: GraphNode[], edges: GraphEdge[]): GraphNode[] => {
    const byId = new Map(nodes.map((node) => [node.id, node]));
    const incoming = new Map<string, string[]>();
    edges.forEach((edge) => {
        const sources = incoming.get(edge.target) ?? [];
        sources.push(edge.source);
        incoming.set(edge.target, sources);
    });

    const seen = new Set<string>();
    const stack = [nodeId];
    const result: GraphNode[] = [];

    while (stack.length > 0) {
        const current = stack.pop()!;
        for (const source of incoming.get(current) ?? []) {
            if (seen.has(source)) {
                continue;
            }
            seen.add(source);
            const node = byId.get(source);
            if (node) {
                result.push(node);
                stack.push(source);
            }
        }
    }

    return result;
};

/**
 * The `{{ ... }}` expressions available inside `nodeId`'s config: everything its
 * upstream nodes provide, plus the workflow variables and `now`.
 */
export const buildExpressionCatalog = (
    nodeId: string | null,
    nodes: GraphNode[],
    edges: GraphEdge[],
    variables: AutomationVariable[],
): ExpressionSuggestion[] => {
    const suggestions: ExpressionSuggestion[] = [];

    if (nodeId) {
        ancestorNodes(nodeId, nodes, edges).forEach((node) => {
            suggestions.push(...providedBy(node));
        });
    }

    variables
        .filter((variable) => variable.key?.trim())
        .forEach((variable) => {
            suggestions.push({ label: `variables.${variable.key}`, info: trans('automations.expr.variable') });
        });

    suggestions.push(suggestion('now', 'now'));

    const seen = new Set<string>();
    return suggestions.filter((item) => (seen.has(item.label) ? false : seen.add(item.label)));
};

/**
 * Whether a `{{ path }}` references something the node can actually resolve.
 * Lenient on purpose: a path is known if it matches a catalog entry, is a
 * parent of one, or is a child of one (e.g. HTTP `fetched.*` is arbitrary, so
 * `fetched` in the catalog makes any `fetched.x.y` valid). When the catalog is
 * empty (no upstream context yet) nothing is flagged.
 */
export const isKnownExpression = (path: string, suggestions: ExpressionSuggestion[]): boolean => {
    if (suggestions.length === 0) {
        return true;
    }

    return suggestions.some(
        (item) =>
            path === item.label ||
            path.startsWith(`${item.label}.`) ||
            item.label.startsWith(`${path}.`),
    );
};

/**
 * CodeMirror completion source that only fires inside a `{{ ... }}` block —
 * matching how n8n scopes completions to its `Resolvable` syntax node. The typed
 * path is replaced with the chosen expression, closing the braces if needed.
 */
export const expressionCompletionSource =
    (getSuggestions: () => ExpressionSuggestion[]) =>
    (context: CompletionContext): CompletionResult | null => {
        const before = context.matchBefore(/\{\{\s*[\w.]*$/);
        if (!before) {
            return null;
        }

        const suggestions = getSuggestions();
        if (suggestions.length === 0) {
            return null;
        }

        // The path token starts after `{{` and any following whitespace.
        const openMarker = before.text.match(/^\{\{\s*/)?.[0] ?? '{{';
        const from = before.from + openMarker.length;

        const options: Completion[] = suggestions.map((item) => ({
            label: item.label,
            info: item.info,
            type: 'variable',
            apply: (view, _completion, applyFrom, applyTo) => {
                const trailing = view.state.sliceDoc(applyTo, applyTo + 3);
                const hasClose = /^\s*\}\}/.test(trailing);
                const insert = hasClose ? item.label : `${item.label} }}`;
                view.dispatch({
                    changes: { from: applyFrom, to: applyTo, insert },
                    selection: { anchor: applyFrom + insert.length },
                });
            },
        }));

        return { from, options, filter: true };
    };
