<script setup lang="ts">
import { IconArrowLeft, IconTrash } from '@tabler/icons-vue';

import BuildPanel from '@/components/automations/BuildPanel.vue';
import TestRunPanel from '@/components/automations/TestRunPanel.vue';
import VariablesPanel from '@/components/automations/VariablesPanel.vue';
import { Button } from '@/components/ui/button';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import type { AutomationVariable } from '@/types/automation/automation';

defineProps<{
    automationId: string;
    beforeRun?: () => Promise<boolean> | boolean;
    configIssue?: string | null;
    editing?: boolean;
    nodeTitle?: string;
    deletable?: boolean;
}>();

const emit = defineEmits<{ back: []; delete: [] }>();

const activeTab = defineModel<string>('tab', { default: 'build' });
const variables = defineModel<AutomationVariable[]>('variables', { default: () => [] });
</script>

<template>
    <aside class="flex w-[26rem] flex-shrink-0 flex-col py-3 pl-2 pr-3">
        <div class="flex h-full min-h-0 flex-col overflow-hidden rounded-2xl border-2 border-foreground bg-card shadow-[3px_3px_0_var(--foreground)]">
            <!-- Editing a node: its config lives inside the same bar, reachable
                 back to Build via the arrow. -->
            <template v-if="editing">
                <div class="flex items-center justify-between gap-2 border-b-2 border-foreground/10 px-3 py-3">
                    <div class="flex min-w-0 items-center gap-1.5">
                        <Button variant="ghost" size="icon-sm" @click="emit('back')">
                            <IconArrowLeft class="size-4" />
                        </Button>
                        <span class="truncate text-sm font-bold capitalize">{{ nodeTitle }}</span>
                    </div>
                    <Button v-if="deletable" variant="ghost" size="icon-sm" @click="emit('delete')">
                        <IconTrash class="size-4" />
                    </Button>
                </div>
                <div class="min-h-0 overflow-y-auto p-4">
                    <slot name="config" />
                </div>
            </template>

            <!-- Default: Build / Test tabs -->
            <Tabs v-else v-model="activeTab" class="flex h-full min-h-0 flex-col">
                <TabsList class="flex h-auto w-full gap-2 border-b-2 border-foreground/10 px-4 py-3">
                    <TabsTrigger value="build" class="flex-1">{{ $t('automations.tabs.build') }}</TabsTrigger>
                    <TabsTrigger value="variables" class="flex-1">
                        {{ $t('automations.tabs.variables') }}<span v-if="variables.length"> ({{ variables.length }})</span>
                    </TabsTrigger>
                    <TabsTrigger value="test" class="flex-1">{{ $t('automations.tabs.test') }}</TabsTrigger>
                </TabsList>

                <TabsContent value="build" class="min-h-0 overflow-y-auto p-4">
                    <BuildPanel />
                </TabsContent>

                <TabsContent value="variables" class="min-h-0 overflow-y-auto p-4">
                    <VariablesPanel v-model="variables" />
                </TabsContent>

                <TabsContent value="test" class="min-h-0 overflow-y-auto">
                    <TestRunPanel :automation-id="automationId" :before-run="beforeRun" :config-issue="configIssue" />
                </TabsContent>
            </Tabs>
        </div>
    </aside>
</template>
