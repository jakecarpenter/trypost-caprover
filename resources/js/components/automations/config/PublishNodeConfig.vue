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
import { PublishMode, type PublishModeValue } from '@/types/automation/publish-mode';

interface PublishConfig {
    mode: PublishModeValue;
    scheduled_offset?: number;
}

const props = defineProps<{
    data: Record<string, unknown>;
    errors?: Record<string, string>;
}>();
const emit = defineEmits<{ update: [Record<string, unknown>] }>();

const local = ref<PublishConfig>({
    mode: (props.data.mode as PublishConfig['mode']) ?? PublishMode.Now,
    scheduled_offset: (props.data.scheduled_offset as number) ?? 60,
});

watch(local, (val) => emit('update', val), { deep: true });
</script>

<template>
    <div class="space-y-3">
        <div>
            <Label class="mb-1 block">{{ $t('automations.config.publish.mode') }}</Label>
            <Select v-model="local.mode">
                <SelectTrigger class="w-full">
                    <SelectValue :placeholder="$t('automations.config.select_placeholder')" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem :value="PublishMode.Now">{{ $t('automations.config.publish.modes.now') }}</SelectItem>
                    <SelectItem :value="PublishMode.Scheduled">{{ $t('automations.config.publish.modes.scheduled') }}</SelectItem>
                    <SelectItem :value="PublishMode.Draft">{{ $t('automations.config.publish.modes.draft') }}</SelectItem>
                </SelectContent>
            </Select>
            <InputError :message="errors?.mode" class="mt-1" />
        </div>

        <div v-if="local.mode === PublishMode.Scheduled">
            <Label class="mb-1 block">{{ $t('automations.config.publish.scheduled_offset') }}</Label>
            <Input type="number" v-model.number="local.scheduled_offset" placeholder="60" />
            <InputError :message="errors?.scheduled_offset" class="mt-1" />
        </div>
    </div>
</template>
