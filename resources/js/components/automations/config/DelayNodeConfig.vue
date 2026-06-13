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
import { DelayUnit, type DelayUnitValue } from '@/types/automation/delay-unit';

interface DelayConfig {
    duration: number;
    unit: DelayUnitValue;
}

const props = defineProps<{
    data: Record<string, unknown>;
    errors?: Record<string, string>;
}>();
const emit = defineEmits<{ update: [Record<string, unknown>] }>();

const local = ref<DelayConfig>({
    duration: (props.data.duration as number) ?? 1,
    unit: (props.data.unit as DelayConfig['unit']) ?? DelayUnit.Hours,
});

watch(local, (val) => emit('update', val), { deep: true });
</script>

<template>
    <div class="space-y-3">
        <div>
            <Label class="mb-1 block">{{ $t('automations.config.delay.duration') }}</Label>
            <Input type="number" v-model.number="local.duration" placeholder="1" />
            <InputError :message="errors?.duration" class="mt-1" />
        </div>

        <div>
            <Label class="mb-1 block">{{ $t('automations.config.delay.unit') }}</Label>
            <Select v-model="local.unit">
                <SelectTrigger class="w-full">
                    <SelectValue :placeholder="$t('automations.config.select_placeholder')" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem :value="DelayUnit.Minutes">{{ $t('automations.config.delay.units.minutes') }}</SelectItem>
                    <SelectItem :value="DelayUnit.Hours">{{ $t('automations.config.delay.units.hours') }}</SelectItem>
                    <SelectItem :value="DelayUnit.Days">{{ $t('automations.config.delay.units.days') }}</SelectItem>
                </SelectContent>
            </Select>
            <InputError :message="errors?.unit" class="mt-1" />
        </div>
    </div>
</template>
