<script setup lang="ts">
import { router, useHttp } from '@inertiajs/vue3';
import { IconCheck, IconCopy, IconLoader2 } from '@tabler/icons-vue';
import { trans } from 'laravel-vue-i18n';
import { onUnmounted, ref, watch } from 'vue';
import { toast } from 'vue-sonner';

import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { copyToClipboard } from '@/lib/utils';
import {
    connect as connectTelegram,
    status as telegramStatus,
} from '@/routes/app/social/telegram';
import {
    TelegramConnectStatus,
    type TelegramConnectStatusValue,
} from '@/types/telegram-connect-status';

const open = defineModel<boolean>('open', { required: true });

type Phase = 'loading' | 'ready' | 'connected' | 'expired' | 'error';

interface ConnectResponse {
    code: string;
    bot_username: string;
    expires_at: string;
}

const POLL_INTERVAL_MS = 3000;
const SUCCESS_CLOSE_DELAY_MS = 1200;

const phase = ref<Phase>('loading');
const code = ref('');
const botUsername = ref('');
const errorMessage = ref('');

const httpConnect = useHttp<Record<string, never>, ConnectResponse>({});
const httpStatus = useHttp<
    Record<string, never>,
    { status: TelegramConnectStatusValue }
>({});

let pollTimer: ReturnType<typeof setTimeout> | null = null;

const stopPolling = () => {
    if (pollTimer !== null) {
        clearTimeout(pollTimer);
        pollTimer = null;
    }
};

const poll = async () => {
    if (phase.value !== 'ready') return;

    try {
        const response = await httpStatus.get(
            telegramStatus.url({ query: { code: code.value } }),
        );

        if (response?.status === TelegramConnectStatus.Connected) {
            phase.value = 'connected';
            stopPolling();
            toast.success(trans('accounts.telegram.connected_toast'));
            setTimeout(() => {
                open.value = false;
                router.reload();
            }, SUCCESS_CLOSE_DELAY_MS);
            return;
        }

        // The signed code expired (or the session was lost): prompt a fresh one.
        if (response?.status === TelegramConnectStatus.Unknown) {
            phase.value = 'expired';
            stopPolling();
            return;
        }
    } catch {
        // Transient polling failures are ignored; the next tick retries.
    }

    pollTimer = setTimeout(poll, POLL_INTERVAL_MS);
};

const start = async () => {
    phase.value = 'loading';
    errorMessage.value = '';

    try {
        const response = await httpConnect.post(connectTelegram.url());
        code.value = response.code;
        botUsername.value = response.bot_username;
        phase.value = 'ready';
        poll();
    } catch (error) {
        phase.value = 'error';
        errorMessage.value =
            (error as { response?: { data?: { message?: string } } })?.response
                ?.data?.message ?? trans('accounts.telegram.error_generic');
    }
};

const copyCommand = () => {
    copyToClipboard(
        `/connect ${code.value}`,
        trans('accounts.telegram.copied_toast'),
    );
};

watch(open, (isOpen) => {
    if (isOpen) {
        start();
    } else {
        stopPolling();
    }
});

onUnmounted(stopPolling);
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <div class="flex items-center gap-3">
                    <img
                        src="/images/accounts/telegram.png"
                        alt="Telegram"
                        class="size-10 rounded-lg"
                    />
                    <div class="text-left">
                        <DialogTitle>{{
                            $t('accounts.telegram.title')
                        }}</DialogTitle>
                        <DialogDescription>{{
                            $t('accounts.telegram.description')
                        }}</DialogDescription>
                    </div>
                </div>
            </DialogHeader>

            <div
                v-if="phase === 'loading'"
                class="flex items-center justify-center py-10"
            >
                <IconLoader2
                    class="size-6 animate-spin text-muted-foreground"
                />
            </div>

            <div v-else-if="phase === 'error'" class="space-y-4 py-2">
                <p class="text-sm text-destructive">{{ errorMessage }}</p>
                <Button class="w-full" @click="start">{{
                    $t('accounts.telegram.retry')
                }}</Button>
            </div>

            <div v-else-if="phase === 'expired'" class="space-y-4 py-2">
                <p class="text-sm text-muted-foreground">
                    {{ $t('accounts.telegram.expired') }}
                </p>
                <Button class="w-full" @click="start">{{
                    $t('accounts.telegram.new_code')
                }}</Button>
            </div>

            <div
                v-else-if="phase === 'connected'"
                class="flex flex-col items-center gap-3 py-8 text-center"
            >
                <span
                    class="inline-flex size-12 items-center justify-center rounded-full bg-emerald-100 text-emerald-600"
                >
                    <IconCheck class="size-6" stroke-width="3" />
                </span>
                <p class="text-sm font-medium">
                    {{ $t('accounts.telegram.connected') }}
                </p>
            </div>

            <div v-else class="min-w-0 space-y-5 py-2">
                <ol class="space-y-4 text-sm">
                    <li class="flex gap-3">
                        <span
                            class="flex size-6 shrink-0 items-center justify-center rounded-full border-2 border-foreground text-xs font-semibold"
                            >1</span
                        >
                        <span>{{
                            trans('accounts.telegram.step_admin', {
                                bot: `@${botUsername}`,
                            })
                        }}</span>
                    </li>
                    <li class="flex gap-3">
                        <span
                            class="flex size-6 shrink-0 items-center justify-center rounded-full border-2 border-foreground text-xs font-semibold"
                            >2</span
                        >
                        <div class="min-w-0 flex-1 space-y-2">
                            <span>{{
                                $t('accounts.telegram.step_command')
                            }}</span>
                            <TooltipProvider>
                                <Tooltip>
                                    <TooltipTrigger as-child>
                                        <button
                                            type="button"
                                            class="group flex w-full cursor-pointer items-center justify-between gap-2 rounded-lg border bg-muted px-3 py-2 text-left font-mono text-sm transition-colors hover:bg-muted/70"
                                            @click="copyCommand"
                                        >
                                            <span class="min-w-0 truncate"
                                                >/connect {{ code }}</span
                                            >
                                            <IconCopy
                                                class="size-4 shrink-0 text-muted-foreground group-hover:text-foreground"
                                            />
                                        </button>
                                    </TooltipTrigger>
                                    <TooltipContent>
                                        <p>
                                            {{
                                                $t(
                                                    'accounts.telegram.copy_tooltip',
                                                )
                                            }}
                                        </p>
                                    </TooltipContent>
                                </Tooltip>
                            </TooltipProvider>
                        </div>
                    </li>
                </ol>

                <div
                    class="flex items-center gap-2 text-sm text-muted-foreground"
                >
                    <IconLoader2 class="size-4 animate-spin" />
                    {{ $t('accounts.telegram.waiting') }}
                </div>
            </div>

            <DialogFooter>
                <Button variant="outline" @click="open = false">{{
                    $t('accounts.telegram.close')
                }}</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
