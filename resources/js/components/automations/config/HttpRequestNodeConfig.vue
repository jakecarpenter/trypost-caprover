<script setup lang="ts">
import { IconPlus, IconTrash } from '@tabler/icons-vue';
import { computed, ref, watch } from 'vue';


import CodeEditor from '@/components/CodeEditor.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
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
import { AuthType, type AuthTypeValue } from '@/types/automation/auth-type';
import { HTTP_METHODS, HTTP_METHODS_WITH_BODY, HttpMethod, type HttpMethodValue } from '@/types/automation/http-method';

type Method = HttpMethodValue;

interface HttpRequestConfig {
    url: string;
    method: Method;
    auth_type: AuthTypeValue;
    auth_token: string;
    auth_username: string;
    auth_password: string;
    auth_header_name: string;
    headers: Record<string, string>;
    body_template: string;
    items_path: string;
    item_key_path: string;
    item_date_path: string;
}

const props = defineProps<{
    data: Record<string, unknown>;
    errors?: Record<string, string>;
}>();
const emit = defineEmits<{ update: [Record<string, unknown>] }>();

const local = ref<HttpRequestConfig>({
    url: (props.data.url as string) ?? '',
    method: (props.data.method as Method) ?? HttpMethod.Get,
    auth_type: (props.data.auth_type as AuthTypeValue) ?? AuthType.None,
    auth_token: (props.data.auth_token as string) ?? '',
    auth_username: (props.data.auth_username as string) ?? '',
    auth_password: (props.data.auth_password as string) ?? '',
    auth_header_name: (props.data.auth_header_name as string) ?? 'X-API-Key',
    headers: (props.data.headers as Record<string, string>) ?? {},
    body_template: (props.data.body_template as string) ?? '',
    items_path: (props.data.items_path as string) ?? '',
    item_key_path: (props.data.item_key_path as string) ?? '',
    item_date_path: (props.data.item_date_path as string) ?? '',
});

interface HeaderRow {
    name: string;
    value: string;
}

const headerRows = ref<HeaderRow[]>(
    Object.entries((props.data.headers as Record<string, string>) ?? {}).map(([name, value]) => ({
        name,
        value: String(value),
    })),
);

watch(
    headerRows,
    (rows) => {
        const headers: Record<string, string> = {};
        rows.forEach((row) => {
            const name = row.name.trim();
            if (name !== '') {
                headers[name] = row.value;
            }
        });
        local.value.headers = headers;
    },
    { deep: true },
);

const addHeader = (): void => {
    headerRows.value.push({ name: '', value: '' });
};

const removeHeader = (index: number): void => {
    headerRows.value.splice(index, 1);
};

watch(local, (val) => emit('update', val), { deep: true });

const editorExpanded = useExpandedEditor();

const supportsBody = computed(() => HTTP_METHODS_WITH_BODY.includes(local.value.method));

const isBodyJsonInvalid = computed(() => {
    const value = local.value.body_template.trim();
    if (value === '') {
        return false;
    }
    try {
        JSON.parse(value);
        return false;
    } catch {
        return true;
    }
});
</script>

<template>
    <div class="space-y-4">
        <div class="grid grid-cols-[110px_1fr] gap-2">
            <div>
                <Label class="mb-1 block">{{ $t('automations.config.http_request.method') }}</Label>
                <Select v-model="local.method">
                    <SelectTrigger class="w-full">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem v-for="m in HTTP_METHODS" :key="m" :value="m">{{ m }}</SelectItem>
                    </SelectContent>
                </Select>
                <InputError :message="errors?.method" class="mt-1" />
            </div>
            <div>
                <Label class="mb-1 block">{{ $t('automations.config.http_request.url') }}</Label>
                <Input v-model="local.url" placeholder="https://api.example.com/items" />
                <InputError :message="errors?.url" class="mt-1" />
            </div>
        </div>

        <div>
            <Label class="mb-1 block">{{ $t('automations.config.http_request.auth_type') }}</Label>
            <Select v-model="local.auth_type">
                <SelectTrigger class="w-full">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem :value="AuthType.None">{{ $t('automations.config.http_request.auth.none') }}</SelectItem>
                    <SelectItem :value="AuthType.Bearer">{{ $t('automations.config.http_request.auth.bearer') }}</SelectItem>
                    <SelectItem :value="AuthType.Basic">{{ $t('automations.config.http_request.auth.basic') }}</SelectItem>
                    <SelectItem :value="AuthType.ApiKey">{{ $t('automations.config.http_request.auth.api_key') }}</SelectItem>
                </SelectContent>
            </Select>
        </div>

        <div v-if="local.auth_type === AuthType.Bearer">
            <Label class="mb-1 block">{{ $t('automations.config.http_request.bearer_token') }}</Label>
            <Input v-model="local.auth_token" type="password" autocomplete="off" placeholder="sk-…" />
            <InputError :message="errors?.auth_token" class="mt-1" />
        </div>

        <template v-if="local.auth_type === AuthType.Basic">
            <div>
                <Label class="mb-1 block">{{ $t('automations.config.http_request.basic_username') }}</Label>
                <Input v-model="local.auth_username" autocomplete="off" />
                <InputError :message="errors?.auth_username" class="mt-1" />
            </div>
            <div>
                <Label class="mb-1 block">{{ $t('automations.config.http_request.basic_password') }}</Label>
                <Input v-model="local.auth_password" type="password" autocomplete="off" />
                <InputError :message="errors?.auth_password" class="mt-1" />
            </div>
        </template>

        <template v-if="local.auth_type === AuthType.ApiKey">
            <div>
                <Label class="mb-1 block">{{ $t('automations.config.http_request.api_key_header') }}</Label>
                <Input v-model="local.auth_header_name" placeholder="X-API-Key" />
            </div>
            <div>
                <Label class="mb-1 block">{{ $t('automations.config.http_request.api_key_value') }}</Label>
                <Input v-model="local.auth_token" type="password" autocomplete="off" />
                <InputError :message="errors?.auth_token" class="mt-1" />
            </div>
        </template>

        <div v-if="supportsBody" v-show="!editorExpanded">
            <Label class="mb-1 block">{{ $t('automations.config.http_request.body_template') }}</Label>
            <div class="h-36">
                <CodeEditor
                    v-model="local.body_template"
                    language="json"
                    expandable
                    :label="$t('automations.config.http_request.body_template')"
                    placeholder='{"id": "{{ trigger.post.id }}"}'
                />
            </div>
            <p v-if="isBodyJsonInvalid" class="mt-1 text-xs text-amber-600 dark:text-amber-500">
                {{ $t('automations.config.invalid_json') }}
            </p>
            <InputError :message="errors?.body_template" class="mt-1" />
        </div>

        <div>
            <Label class="mb-1 block">{{ $t('automations.config.http_request.headers') }}</Label>
            <div class="space-y-2">
                <div v-for="(row, index) in headerRows" :key="index" class="flex items-center gap-2">
                    <Input
                        v-model="row.name"
                        class="flex-1"
                        :placeholder="$t('automations.config.http_request.header_name')"
                    />
                    <Input
                        v-model="row.value"
                        class="flex-1"
                        :placeholder="$t('automations.config.http_request.header_value')"
                    />
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        class="shrink-0 text-foreground/60 hover:text-destructive"
                        @click="removeHeader(index)"
                    >
                        <IconTrash class="size-4" />
                    </Button>
                </div>
            </div>
            <Button type="button" variant="outline" size="sm" class="mt-2" @click="addHeader">
                <IconPlus class="size-4" />
                {{ $t('automations.config.http_request.add_header') }}
            </Button>
        </div>

        <div class="border-t-2 border-foreground/10 pt-4">
            <p class="mb-2 text-[11px] font-black uppercase tracking-widest text-foreground/60">
                {{ $t('automations.config.http_request.polling_section') }}
            </p>
            <p class="mb-3 text-xs text-foreground/60">
                {{ $t('automations.config.http_request.polling_hint') }}
            </p>

            <div class="space-y-3">
                <div>
                    <Label class="mb-1 block">{{ $t('automations.config.http_request.items_path') }}</Label>
                    <Input v-model="local.items_path" placeholder="data.items" />
                    <p class="mt-1 text-xs text-foreground/50">{{ $t('automations.config.http_request.items_path_hint') }}</p>
                    <InputError :message="errors?.items_path" class="mt-1" />
                </div>
                <div>
                    <Label class="mb-1 block">{{ $t('automations.config.http_request.item_date_path') }}</Label>
                    <Input v-model="local.item_date_path" placeholder="published_at" />
                    <p class="mt-1 text-xs text-foreground/50">{{ $t('automations.config.http_request.item_date_path_hint') }}</p>
                    <InputError :message="errors?.item_date_path" class="mt-1" />
                </div>
                <div>
                    <Label class="mb-1 block">{{ $t('automations.config.http_request.item_key_path') }}</Label>
                    <Input v-model="local.item_key_path" placeholder="id" />
                    <p class="mt-1 text-xs text-foreground/50">{{ $t('automations.config.http_request.item_key_path_hint') }}</p>
                    <InputError :message="errors?.item_key_path" class="mt-1" />
                </div>
            </div>
        </div>
    </div>
</template>
