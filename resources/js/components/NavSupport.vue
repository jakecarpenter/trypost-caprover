<script setup lang="ts">
import type { Component } from 'vue';

import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';

interface SupportNavItem {
    title: string;
    icon: Component;
    href: string;
}

interface Props {
    items: SupportNavItem[];
    label?: string;
}

defineProps<Props>();
</script>

<template>
    <SidebarGroup class="px-2 py-0">
        <SidebarGroupLabel v-if="label">{{ label }}</SidebarGroupLabel>
        <SidebarMenu>
            <SidebarMenuItem v-for="item in items" :key="item.title">
                <SidebarMenuButton as-child :tooltip="item.title">
                    <a :href="item.href" target="_blank" rel="noopener noreferrer">
                        <component :is="item.icon" />
                        <span>{{ item.title }}</span>
                    </a>
                </SidebarMenuButton>
            </SidebarMenuItem>
        </SidebarMenu>
    </SidebarGroup>
</template>
