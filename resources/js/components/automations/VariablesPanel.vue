<script setup lang="ts">
import { IconCopy, IconPlus, IconTrash } from '@tabler/icons-vue';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { copyToClipboard } from '@/lib/utils';
import type { AutomationVariable } from '@/types/automation/automation';

const variables = defineModel<AutomationVariable[]>({ default: () => [] });

const usage = (key: string): string => `{{ variables.${key} }}`;

const addVariable = () => {
    variables.value = [...variables.value, { key: '', value: '' }];
};

const removeVariable = (index: number) => {
    variables.value = variables.value.filter((_, i) => i !== index);
};

const updateKey = (index: number, key: string) => {
    variables.value = variables.value.map((variable, i) => (i === index ? { ...variable, key } : variable));
};

const updateValue = (index: number, value: string) => {
    variables.value = variables.value.map((variable, i) => (i === index ? { ...variable, value } : variable));
};
</script>

<template>
    <div class="flex flex-col gap-4">
        <div>
            <p class="text-[11px] font-black uppercase tracking-widest text-foreground/50">{{ $t('automations.variables.title') }}</p>
            <p class="mt-1 text-xs text-foreground/60">{{ $t('automations.variables.hint') }}</p>
        </div>

        <div
            v-if="variables.length === 0"
            class="rounded-xl border-2 border-dashed border-foreground/25 bg-card/40 p-6 text-center text-sm font-medium text-foreground/60"
        >
            {{ $t('automations.variables.empty') }}
        </div>

        <div
            v-for="(variable, index) in variables"
            :key="index"
            class="space-y-2 rounded-xl border-2 border-foreground bg-card p-3 shadow-2xs"
        >
            <div class="flex items-start gap-2">
                <div class="flex-1 space-y-1">
                    <Label class="text-[11px] font-bold uppercase tracking-wider text-foreground/60">{{ $t('automations.variables.key') }}</Label>
                    <Input
                        :model-value="variable.key"
                        :placeholder="$t('automations.variables.key_placeholder')"
                        @update:model-value="updateKey(index, String($event))"
                    />
                </div>
                <Button variant="ghost" size="icon-sm" class="mt-6" @click="removeVariable(index)">
                    <IconTrash class="size-4" />
                </Button>
            </div>

            <div class="space-y-1">
                <Label class="text-[11px] font-bold uppercase tracking-wider text-foreground/60">{{ $t('automations.variables.value') }}</Label>
                <Input
                    :model-value="variable.value"
                    :placeholder="$t('automations.variables.value_placeholder')"
                    @update:model-value="updateValue(index, String($event))"
                />
            </div>

            <button
                v-if="variable.key"
                type="button"
                class="inline-flex items-center gap-1.5 font-mono text-xs text-foreground/55 transition-colors hover:text-foreground"
                @click="copyToClipboard(usage(variable.key))"
            >
                <IconCopy class="size-3.5" />
                {{ usage(variable.key) }}
            </button>
        </div>

        <Button variant="outline" size="sm" class="self-start" @click="addVariable">
            <IconPlus class="size-4" />
            {{ $t('automations.variables.add') }}
        </Button>
    </div>
</template>
