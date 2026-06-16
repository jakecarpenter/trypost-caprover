<script setup lang="ts">
import { computed } from 'vue';

import VideoPreview from '@/components/posts/previews/VideoPreview.vue';
import { isVideoMedia } from '@/composables/useMedia';
import type { MediaItem } from '@/types/media';

interface SocialAccount {
    id: string;
    platform: string;
    display_name: string;
    username: string;
    avatar_url: string | null;
}

interface EmbedDraft {
    title?: string;
    description?: string;
    url?: string;
    image?: string;
    color?: string;
}

const props = defineProps<{
    socialAccount: SocialAccount;
    content: string;
    media: MediaItem[];
    meta?: Record<string, any>;
}>();

const embeds = computed<EmbedDraft[]>(() => (Array.isArray(props.meta?.embeds) ? (props.meta!.embeds as EmbedDraft[]) : []));
</script>

<template>
    <div class="flex h-full w-full flex-col overflow-hidden bg-[#313338]">
        <!-- Channel header -->
        <div class="flex items-center gap-2 border-b border-black/20 px-4 py-2.5 text-[#f2f3f5]">
            <span class="text-xl leading-none text-[#80848e]">#</span>
            <span class="truncate text-[15px] font-semibold">{{ socialAccount.display_name || 'channel' }}</span>
        </div>

        <!-- Message -->
        <div class="flex-1 overflow-y-auto px-4 py-4">
            <div class="flex gap-3">
                <img
                    v-if="socialAccount.avatar_url"
                    :src="socialAccount.avatar_url"
                    :alt="socialAccount.display_name"
                    class="h-10 w-10 shrink-0 rounded-full object-cover"
                />
                <div
                    v-else
                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-[#5865F2] font-semibold text-white"
                >
                    {{ socialAccount.display_name?.charAt(0) }}
                </div>

                <div class="min-w-0 flex-1">
                    <div class="flex items-baseline gap-2">
                        <span class="text-[15px] font-medium text-white">{{ socialAccount.display_name || 'TryPost' }}</span>
                        <span class="rounded bg-[#5865F2] px-1 text-[10px] font-bold uppercase tracking-wide text-white">Bot</span>
                        <span class="text-[11px] text-[#949ba4]">Today at 4:30 PM</span>
                    </div>

                    <p v-if="content" class="mt-0.5 whitespace-pre-wrap text-[15px] leading-[1.375] text-[#dbdee1]">{{ content }}</p>

                    <!-- Media -->
                    <div
                        v-if="media.length > 0"
                        class="mt-2 overflow-hidden rounded-lg"
                        :class="{ 'grid grid-cols-2 gap-0.5': media.length >= 2 }"
                    >
                        <div
                            v-for="(item, index) in media.slice(0, 4)"
                            :key="item.id"
                            class="relative overflow-hidden"
                            :class="media.length === 1 ? 'aspect-[4/3] max-w-sm' : 'aspect-square'"
                        >
                            <img
                                v-if="!isVideoMedia(item)"
                                :src="item.url"
                                :alt="item.original_filename"
                                class="h-full w-full object-cover"
                            />
                            <VideoPreview v-else :src="item.url" video-class="w-full h-full object-cover bg-black" />
                            <div
                                v-if="media.length > 4 && index === 3"
                                class="absolute inset-0 flex items-center justify-center bg-black/60"
                            >
                                <span class="text-xl font-semibold text-white">+{{ media.length - 4 }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Embeds -->
                    <div
                        v-for="(embed, index) in embeds"
                        :key="index"
                        class="mt-2 max-w-md overflow-hidden rounded border-l-4 bg-[#2b2d31] p-3"
                        :style="{ borderColor: embed.color || '#5865F2' }"
                    >
                        <p v-if="embed.title" class="text-[15px] font-semibold text-[#00a8fc]">{{ embed.title }}</p>
                        <p v-if="embed.description" class="mt-1 whitespace-pre-wrap text-[14px] leading-[1.3] text-[#dbdee1]">{{ embed.description }}</p>
                        <img v-if="embed.image" :src="embed.image" alt="" class="mt-2 max-h-48 rounded object-cover" />
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
