<script setup lang="ts">
import { ref, watch } from 'vue';

import InputError from '@/components/InputError.vue';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';

interface EndConfig {
    reason: string;
}

const props = defineProps<{
    data: Record<string, unknown>;
    errors?: Record<string, string>;
}>();
const emit = defineEmits<{ update: [Record<string, unknown>] }>();

const local = ref<EndConfig>({
    reason: (props.data.reason as string) ?? '',
});

watch(local, (val) => emit('update', val), { deep: true });
</script>

<template>
    <div class="space-y-3">
        <div>
            <Label class="mb-1 block">{{ $t('automations.config.end.reason') }}</Label>
            <Textarea v-model="local.reason" :placeholder="$t('automations.config.end.reason_placeholder')" :rows="3" />
            <InputError :message="errors?.reason" class="mt-1" />
        </div>
        <p class="text-xs text-muted-foreground">{{ $t('automations.nodes.end_summary') }}</p>
    </div>
</template>
