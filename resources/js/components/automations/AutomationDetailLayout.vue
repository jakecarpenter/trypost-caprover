<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

import AutomationHeader from '@/components/automations/AutomationHeader.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { Automation } from '@/types/automation/automation';

defineProps<{
    automation: Automation;
    current: 'workflow' | 'invocations' | 'metrics' | 'settings';
}>();
</script>

<template>
    <Head :title="automation.name" />

    <AppLayout full-width>
        <div class="flex min-h-0 flex-1 flex-col bg-background">
            <AutomationHeader :automation="automation" :current="current" />

            <div class="min-h-0 flex-1 overflow-y-auto">
                <slot />
            </div>
        </div>
    </AppLayout>
</template>
