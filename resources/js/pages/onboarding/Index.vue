<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import {
    IconArrowRight,
    IconBriefcase,
    IconBuildingSkyscraper,
    IconBuildingStore,
    IconCheck,
    IconDots,
    IconRocket,
    IconUser,
} from '@tabler/icons-vue';
import { trans } from 'laravel-vue-i18n';
import type { FunctionalComponent } from 'vue';

import { Button } from '@/components/ui/button';
import { store } from '@/routes/app/onboarding';

const props = defineProps<{
    personas: string[];
    selected?: string | null;
}>();

const form = useForm({ persona: props.selected ?? '' });

const personaMeta: Record<string, { icon: FunctionalComponent; color: string }> = {
    creator: { icon: IconUser, color: 'text-rose-600' },
    freelancer: { icon: IconBriefcase, color: 'text-amber-600' },
    startup: { icon: IconRocket, color: 'text-violet-700' },
    agency: { icon: IconBuildingSkyscraper, color: 'text-blue-700' },
    small_business: { icon: IconBuildingStore, color: 'text-emerald-600' },
    other: { icon: IconDots, color: 'text-sky-600' },
};

const personaIcon = (value: string): FunctionalComponent => personaMeta[value]?.icon ?? IconDots;

const personaColor = (value: string): string => personaMeta[value]?.color ?? 'text-foreground';

const personaLabel = (value: string): string => trans(`onboarding.personas.${value}`);

const select = (value: string): void => {
    form.persona = value;
};

const submit = (): void => {
    if (!form.persona || form.processing) {
        return;
    }

    form.post(store.url());
};
</script>

<template>
    <Head :title="$t('onboarding.title')" />

    <section class="relative min-h-screen overflow-hidden bg-background">
        <div
            class="pointer-events-none absolute inset-0 opacity-[0.06]"
            style="background-image: radial-gradient(circle, #0a0a0a 1px, transparent 1px); background-size: 28px 28px;"
        />
        <div class="pointer-events-none absolute -top-20 right-0 size-[560px] rounded-full bg-violet-200/50 blur-3xl" />

        <div class="relative mx-auto flex min-h-screen max-w-3xl flex-col justify-center px-6 py-12">
            <div class="mx-auto mb-10 max-w-xl space-y-3 text-center">
                <h1
                    class="text-balance text-3xl font-normal leading-[1.1] tracking-tight text-foreground sm:text-4xl"
                    style="font-family: var(--font-display);"
                >
                    {{ $t('onboarding.title') }}
                </h1>
                <p class="text-balance text-base text-muted-foreground">
                    {{ $t('onboarding.description') }}
                </p>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <button
                    v-for="persona in personas"
                    :key="persona"
                    type="button"
                    :class="[
                        'relative flex cursor-pointer flex-col items-start gap-3 rounded-2xl border-2 border-foreground p-5 text-left shadow-2xs transition-shadow hover:shadow-md',
                        form.persona === persona ? 'bg-violet-100' : 'bg-card',
                    ]"
                    @click="select(persona)"
                >
                    <span class="inline-flex size-10 items-center justify-center rounded-2xl border-2 border-foreground bg-card shadow-2xs">
                        <component
                            :is="personaIcon(persona)"
                            :class="[personaColor(persona), 'size-5']"
                            stroke-width="2.25"
                        />
                    </span>
                    <span class="text-base font-bold tracking-tight text-foreground">
                        {{ personaLabel(persona) }}
                    </span>
                    <span
                        v-if="form.persona === persona"
                        class="absolute right-4 top-4 inline-flex size-5 items-center justify-center rounded-full border-2 border-foreground bg-foreground"
                    >
                        <IconCheck class="size-3 text-background" stroke-width="3" />
                    </span>
                </button>
            </div>

            <div class="mx-auto mt-10 flex w-full max-w-sm flex-col items-center gap-3">
                <Button
                    type="button"
                    size="lg"
                    class="w-full rounded-full"
                    :disabled="!form.persona || form.processing"
                    @click="submit"
                >
                    {{ $t('onboarding.continue') }}
                    <IconArrowRight class="size-4" />
                </Button>
            </div>
        </div>
    </section>
</template>
