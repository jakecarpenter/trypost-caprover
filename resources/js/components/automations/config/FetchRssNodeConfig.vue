<script setup lang="ts">
import { ref, watch } from 'vue';

import InputError from '@/components/InputError.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

interface FetchRssConfig {
    feed_url: string;
}

const props = defineProps<{
    data: Record<string, unknown>;
    errors?: Record<string, string>;
}>();
const emit = defineEmits<{ update: [Record<string, unknown>] }>();

const local = ref<FetchRssConfig>({
    feed_url: (props.data.feed_url as string) ?? '',
});

watch(local, (val) => emit('update', val), { deep: true });
</script>

<template>
    <div class="space-y-3">
        <div>
            <Label class="mb-1 block">{{ $t('automations.config.fetch_rss.feed_url') }}</Label>
            <Input v-model="local.feed_url" placeholder="https://example.com/feed.xml" />
            <InputError :message="errors?.feed_url" class="mt-1" />
            <p class="mt-1 text-xs text-foreground/50">{{ $t('automations.config.fetch_rss.feed_url_hint') }}</p>
        </div>
    </div>
</template>
