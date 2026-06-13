<script setup lang="ts">
import { computed, ref, watch } from 'vue';

import { isPayloadTemplateValid } from '@/components/automations/config-validation';
import CodeEditor from '@/components/CodeEditor.vue';
import InputError from '@/components/InputError.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useExpandedEditor } from '@/composables/useExpandedEditor';
import { HTTP_METHODS, HttpMethod, type HttpMethodValue } from '@/types/automation/http-method';

interface WebhookConfig {
    url: string;
    method: HttpMethodValue;
    headers?: Record<string, string>;
    payload_template: string;
}

const props = defineProps<{
    data: Record<string, unknown>;
    errors?: Record<string, string>;
}>();
const emit = defineEmits<{ update: [Record<string, unknown>] }>();

const editorExpanded = useExpandedEditor();

const local = ref<WebhookConfig>({
    url: (props.data.url as string) ?? '',
    method: (props.data.method as WebhookConfig['method']) ?? HttpMethod.Post,
    headers: (props.data.headers as Record<string, string>) ?? {},
    payload_template: (props.data.payload_template as string) ?? '{}',
});

watch(local, (val) => emit('update', val), { deep: true });

const isPayloadJsonInvalid = computed(() => !isPayloadTemplateValid(local.value.payload_template));
</script>

<template>
    <div class="space-y-3">
        <div>
            <Label class="mb-1 block">{{ $t('automations.config.webhook.url') }}</Label>
            <Input v-model="local.url" placeholder="https://hooks.example.com/…" />
            <InputError :message="errors?.url" class="mt-1" />
        </div>

        <div>
            <Label class="mb-1 block">{{ $t('automations.config.webhook.method') }}</Label>
            <Select v-model="local.method">
                <SelectTrigger class="w-full">
                    <SelectValue :placeholder="$t('automations.config.select_placeholder')" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem v-for="m in HTTP_METHODS" :key="m" :value="m">{{ m }}</SelectItem>
                </SelectContent>
            </Select>
            <InputError :message="errors?.method" class="mt-1" />
        </div>

        <div v-show="!editorExpanded">
            <Label class="mb-1 block">{{ $t('automations.config.webhook.payload_template') }}</Label>
            <div class="h-40">
                <CodeEditor
                    v-model="local.payload_template"
                    language="json"
                    expandable
                    :label="$t('automations.config.webhook.payload_template')"
                    placeholder='{"content": "{{ post.content }}"}'
                />
            </div>
            <p v-if="isPayloadJsonInvalid" class="mt-1 text-xs text-amber-600 dark:text-amber-500">
                {{ $t('automations.config.invalid_json') }}
            </p>
            <InputError :message="errors?.payload_template" class="mt-1" />
        </div>
    </div>
</template>
