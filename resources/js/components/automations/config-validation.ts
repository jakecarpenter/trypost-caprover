import { trans } from 'laravel-vue-i18n';

import { NodeType } from '@/types/automation/node-type';

interface WorkflowNode {
    type?: string;
    data?: Record<string, unknown> | null;
}

/**
 * Mirrors the backend WebhookNodeValidator: a webhook payload template must be
 * valid JSON because the runtime parses it before resolving `{{ }}` placeholders.
 * An empty or literal-`null` template means "no body" and is valid.
 */
export const isPayloadTemplateValid = (template: string): boolean => {
    const trimmed = template.trim();

    if (trimmed === '' || trimmed === 'null') {
        return true;
    }

    try {
        JSON.parse(trimmed);
        return true;
    } catch {
        return false;
    }
};

/**
 * First node-config issue that would block a run, or null when every node is
 * runnable. The frontend gate intentionally covers only the Webhook node — the
 * Generate node has its own inline compliance UI, and the backend
 * AutomationConfigValidator remains the safety net for everything.
 */
export const firstConfigIssue = (nodes: WorkflowNode[]): string | null => {
    for (const node of nodes) {
        if (node.type === NodeType.Webhook && !isPayloadTemplateValid(String(node.data?.payload_template ?? ''))) {
            return trans('automations.errors.webhook_invalid_payload_json');
        }
    }

    return null;
};
