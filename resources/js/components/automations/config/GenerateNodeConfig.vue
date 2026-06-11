<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

import ChannelConfigurator from '@/components/ChannelConfigurator.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { getMediaRulesForContentType } from '@/composables/useMediaRules';
import { getMediaIncompatibilityReason, getPlatformMetaIssue } from '@/composables/usePostCompliance';
import type { PinterestBoard } from '@/types';
import type { Channel } from '@/types/channel';
import { ContentType } from '@/types/content-type';
import type { MediaItem } from '@/types/media';
import { Platform } from '@/types/platform';

interface SocialAccount {
    id: string;
    platform: string;
    display_name: string;
    username: string;
    avatar_url: string | null;
}

interface TikTokCreatorInfo {
    creator_nickname: string | null;
    creator_username: string | null;
    creator_avatar_url: string | null;
    privacy_level_options: string[];
    comment_disabled: boolean;
    duet_disabled: boolean;
    stitch_disabled: boolean;
    max_video_post_duration_sec: number | null;
}

interface GenerateAccount {
    social_account_id: string;
    content_type: string;
    meta: Record<string, any>;
}

interface GenerateConfig {
    accounts: GenerateAccount[];
    target_slide_count: number;
    prompt_template: string;
    include_image: boolean;
}

const props = defineProps<{
    data: Record<string, unknown>;
    errors?: Record<string, string>;
}>();
const emit = defineEmits<{ update: [Record<string, unknown>] }>();

const page = usePage();

const socialAccounts = computed<SocialAccount[]>(() => {
    const raw = page.props.socialAccounts as { data?: SocialAccount[] } | SocialAccount[] | undefined;
    if (!raw) return [];
    return Array.isArray(raw) ? (raw as SocialAccount[]) : ((raw as { data: SocialAccount[] }).data ?? []);
});

const platformConfigs = computed<Record<string, any>>(() => {
    const raw = page.props.platformConfigs as Record<string, any> | undefined;
    return raw ?? {};
});

const pinterestBoards = computed<Record<string, PinterestBoard[]>>(() => {
    const raw = page.props.pinterestBoards as Record<string, PinterestBoard[]> | undefined;
    return raw ?? {};
});

const tiktokCreatorInfos = computed<Record<string, TikTokCreatorInfo>>(() => {
    const raw = page.props.tiktokCreatorInfos as Record<string, TikTokCreatorInfo> | null | undefined;
    return raw ?? {};
});

const defaultContentTypeFor = (platform: string): string => {
    switch (platform) {
        case Platform.Instagram:
        case Platform.InstagramFacebook:
            return ContentType.InstagramFeed;
        case Platform.Facebook:
            return ContentType.FacebookPost;
        case Platform.LinkedIn:
            return ContentType.LinkedInPost;
        case Platform.LinkedInPage:
            return ContentType.LinkedInPagePost;
        case Platform.TikTok:
            return ContentType.TikTokVideo;
        case Platform.Pinterest:
            return ContentType.PinterestPin;
        case Platform.YouTube:
            return ContentType.YouTubeShort;
        case Platform.X:
            return ContentType.XPost;
        case Platform.Threads:
            return ContentType.ThreadsPost;
        case Platform.Bluesky:
            return ContentType.BlueskyPost;
        case Platform.Mastodon:
            return ContentType.MastodonPost;
        default:
            return '';
    }
};

const accountById = (id: string): SocialAccount | undefined =>
    socialAccounts.value.find((a) => a.id === id);

const normalizeAccountsFromData = (): GenerateAccount[] => {
    const incoming = props.data.accounts;
    if (Array.isArray(incoming)) {
        return (incoming as any[]).map((a) => ({
            social_account_id: String(a.social_account_id ?? ''),
            content_type: typeof a.content_type === 'string' && a.content_type
                ? a.content_type
                : defaultContentTypeFor(accountById(String(a.social_account_id ?? ''))?.platform ?? ''),
            meta: (a.meta as Record<string, any>) ?? {},
        })).filter((a) => a.social_account_id);
    }
    // Backward-compat with the old shape (social_account_ids: string[]).
    const legacyIds = props.data.social_account_ids;
    if (Array.isArray(legacyIds)) {
        return (legacyIds as string[]).map((id) => ({
            social_account_id: id,
            content_type: defaultContentTypeFor(accountById(id)?.platform ?? ''),
            meta: {},
        }));
    }
    return [];
};

const local = ref<GenerateConfig>({
    accounts: normalizeAccountsFromData(),
    target_slide_count: (props.data.target_slide_count as number | undefined) ?? 5,
    prompt_template: (props.data.prompt_template as string) ?? '',
    include_image: (props.data.include_image as boolean | undefined) ?? true,
});

watch(local, (val) => emit('update', val), { deep: true });

const selectedAccountIds = computed(() => local.value.accounts.map((a) => a.social_account_id));

const onToggleAccount = (accountId: string) => {
    const account = accountById(accountId);
    if (account) toggleAccount(account);
};

const toggleAccount = (account: SocialAccount) => {
    if (selectedAccountIds.value.includes(account.id)) {
        local.value.accounts = local.value.accounts.filter((a) => a.social_account_id !== account.id);
        return;
    }
    local.value.accounts = [
        ...local.value.accounts,
        {
            social_account_id: account.id,
            content_type: defaultContentTypeFor(account.platform),
            meta: {},
        },
    ];
};

const updateContentType = (accountId: string, value: string) => {
    const idx = local.value.accounts.findIndex((a) => a.social_account_id === accountId);
    if (idx === -1) return;
    local.value.accounts[idx] = { ...local.value.accounts[idx], content_type: value };
};

const updateMeta = (accountId: string, value: Record<string, any>) => {
    const idx = local.value.accounts.findIndex((a) => a.social_account_id === accountId);
    if (idx === -1) return;
    local.value.accounts[idx] = { ...local.value.accounts[idx], meta: value };
};

const getPublishConfig = (account: SocialAccount): Record<string, any> | null =>
    platformConfigs.value[account.id]?.publishConfig ?? null;

const getCreatorInfo = (account: SocialAccount): TikTokCreatorInfo | null =>
    tiktokCreatorInfos.value[account.id] ?? null;

const getBoards = (account: SocialAccount): PinterestBoard[] =>
    pinterestBoards.value[account.id] ?? [];

// Image-capability is derived from the SAME media rules the post editor uses
// (per content type), never a hardcoded list — facebook_post, tiktok_photo,
// linkedin_carousel etc. all accept multiple images. We cap AI image generation
// at MAX_GENERATED_IMAGES regardless of how many a platform technically allows.
const MAX_GENERATED_IMAGES = 10;

const multiImageAccounts = computed(() =>
    local.value.accounts.filter((a) => {
        const rules = getMediaRulesForContentType(a.content_type);
        return rules.acceptImages && rules.maxFiles > 1;
    }),
);

const supportsMultiImage = computed(() => multiImageAccounts.value.length > 0);

const imageCountCap = computed(() => {
    if (!supportsMultiImage.value) return 1;
    const maxAcrossAccounts = Math.max(
        ...multiImageAccounts.value.map((a) => getMediaRulesForContentType(a.content_type).maxFiles),
    );
    return Math.min(MAX_GENERATED_IMAGES, maxAcrossAccounts);
});

const imageCountOptions = computed(() =>
    Array.from({ length: Math.max(0, imageCountCap.value - 1) }, (_, i) => i + 2),
);

// How many images each selected account will receive — the carousel slide count
// when any account supports multiple, otherwise a single image when enabled.
const intendedImageCount = computed(() =>
    supportsMultiImage.value
        ? local.value.target_slide_count
        : (local.value.include_image ? 1 : 0),
);

const syntheticImages = (count: number): MediaItem[] =>
    Array.from({ length: Math.max(0, count) }, () => ({ type: 'image' }) as MediaItem);

// Reuses the post editor's exact compliance: per-content-type media rules
// (too many / too few images, image-not-supported, media-required) AND the
// platform meta rules (TikTok privacy/disclosure, Pinterest board required).
const accountIssue = (accountId: string): string | null => {
    const entry = local.value.accounts.find((a) => a.social_account_id === accountId);
    if (!entry) return null;
    const mediaIssue = getMediaIncompatibilityReason(entry.content_type, syntheticImages(intendedImageCount.value));
    if (mediaIssue) return mediaIssue;
    const account = accountById(accountId);
    return account ? getPlatformMetaIssue(account.platform, entry.meta) : null;
};

watch(imageCountCap, (cap) => {
    if (local.value.target_slide_count > cap) {
        local.value.target_slide_count = cap;
    }
    if (local.value.target_slide_count < 2) {
        local.value.target_slide_count = Math.min(2, cap);
    }
});

const channels = computed<Channel[]>(() =>
    socialAccounts.value.map((account) => {
        const entry = local.value.accounts.find((a) => a.social_account_id === account.id);
        return {
            id: account.id,
            platform: account.platform,
            displayName: account.display_name,
            username: account.username,
            avatarUrl: account.avatar_url,
            socialAccount: account,
            contentType: entry?.content_type ?? defaultContentTypeFor(account.platform),
            meta: entry?.meta ?? {},
            issue: accountIssue(account.id),
            publishConfig: getPublishConfig(account),
            creatorInfo: getCreatorInfo(account),
            boards: getBoards(account),
        };
    }),
);
</script>

<template>
    <div class="space-y-4">
        <div class="space-y-2">
            <Label class="text-sm font-bold">{{ $t('automations.config.generate.social_accounts') }}</Label>
            <InputError :message="errors?.accounts" />
            <p v-if="socialAccounts.length === 0" class="text-xs text-foreground/60">
                {{ $t('automations.config.generate.social_accounts_empty') }}
            </p>
            <ChannelConfigurator
                v-else
                :channels="channels"
                :selected-ids="selectedAccountIds"
                :preview-only="true"
                @toggle="onToggleAccount"
                @update:content-type="updateContentType"
                @update:meta="updateMeta"
            />
        </div>

        <div v-if="supportsMultiImage" class="space-y-2">
            <Label class="text-sm font-bold">{{ $t('automations.config.generate.target_slide_count') }}</Label>
            <div class="flex flex-wrap gap-2">
                <Button
                    v-for="n in imageCountOptions"
                    :key="n"
                    type="button"
                    size="icon"
                    :variant="local.target_slide_count === n ? 'default' : 'outline'"
                    @click="local.target_slide_count = n"
                >
                    {{ n }}
                </Button>
            </div>
        </div>

        <div v-else class="flex items-start justify-between gap-3">
            <div class="space-y-0.5">
                <Label class="text-sm font-bold">{{ $t('automations.config.generate.include_image') }}</Label>
                <p class="text-xs text-foreground/60">{{ $t('automations.config.generate.include_image_hint') }}</p>
            </div>
            <Switch v-model="local.include_image" />
        </div>

        <div>
            <label class="mb-1 block text-sm font-medium">{{ $t('automations.config.generate.prompt_template') }}</label>
            <Textarea v-model="local.prompt_template" :rows="6" placeholder="Write a social media post about {{ trigger.title }}…" />
            <InputError :message="errors?.prompt_template" class="mt-1" />
        </div>
    </div>
</template>
