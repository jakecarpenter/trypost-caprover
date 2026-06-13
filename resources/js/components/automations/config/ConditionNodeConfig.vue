<script setup lang="ts">
import { ref, watch } from 'vue';

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
import { ConditionOperator, type ConditionOperatorValue } from '@/types/automation/condition-operator';

interface ConditionConfig {
    field: string;
    operator: ConditionOperatorValue;
    value: string;
}

const props = defineProps<{
    data: Record<string, unknown>;
    errors?: Record<string, string>;
}>();
const emit = defineEmits<{ update: [Record<string, unknown>] }>();

const local = ref<ConditionConfig>({
    field: (props.data.field as string) ?? '',
    operator: (props.data.operator as ConditionOperatorValue) ?? ConditionOperator.Contains,
    value: (props.data.value as string) ?? '',
});

watch(local, (val) => emit('update', val), { deep: true });
</script>

<template>
    <div class="space-y-3">
        <div>
            <Label class="mb-1 block">{{ $t('automations.config.condition.field') }}</Label>
            <Input v-model="local.field" placeholder="{{ trigger.title }}" />
            <InputError :message="errors?.field" class="mt-1" />
        </div>

        <div>
            <Label class="mb-1 block">{{ $t('automations.config.condition.operator') }}</Label>
            <Select v-model="local.operator">
                <SelectTrigger class="w-full">
                    <SelectValue :placeholder="$t('automations.config.select_placeholder')" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem :value="ConditionOperator.Contains">{{ $t('automations.config.condition.operators.contains') }}</SelectItem>
                    <SelectItem :value="ConditionOperator.NotContains">{{ $t('automations.config.condition.operators.not_contains') }}</SelectItem>
                    <SelectItem :value="ConditionOperator.Equals">{{ $t('automations.config.condition.operators.equals') }}</SelectItem>
                    <SelectItem :value="ConditionOperator.NotEquals">{{ $t('automations.config.condition.operators.not_equals') }}</SelectItem>
                    <SelectItem :value="ConditionOperator.Matches">{{ $t('automations.config.condition.operators.matches') }}</SelectItem>
                    <SelectItem :value="ConditionOperator.GreaterThan">{{ $t('automations.config.condition.operators.greater_than') }}</SelectItem>
                    <SelectItem :value="ConditionOperator.LessThan">{{ $t('automations.config.condition.operators.less_than') }}</SelectItem>
                </SelectContent>
            </Select>
            <InputError :message="errors?.operator" class="mt-1" />
        </div>

        <div>
            <Label class="mb-1 block">{{ $t('automations.config.condition.value') }}</Label>
            <Input v-model="local.value" placeholder="keyword" />
            <InputError :message="errors?.value" class="mt-1" />
        </div>
    </div>
</template>
