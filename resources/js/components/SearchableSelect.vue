<script setup lang="ts">
import { IconCheck, IconChevronDown } from '@tabler/icons-vue';
import { computed, ref } from 'vue';

import { Button } from '@/components/ui/button';
import { Command, CommandEmpty, CommandGroup, CommandInput, CommandItem, CommandList } from '@/components/ui/command';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { cn } from '@/lib/utils';

interface Option {
    value: string;
    label: string;
}

const props = withDefaults(
    defineProps<{
        options: Option[];
        placeholder?: string;
        searchPlaceholder?: string;
        emptyText?: string;
        disabled?: boolean;
        invalid?: boolean;
    }>(),
    {
        placeholder: 'Select…',
        searchPlaceholder: 'Search…',
        emptyText: 'No results.',
        disabled: false,
        invalid: false,
    },
);

const value = defineModel<string>({ default: '' });

const open = ref(false);
const selected = computed(() => props.options.find((option) => option.value === value.value));

const select = (option: Option) => {
    value.value = option.value;
    open.value = false;
};
</script>

<template>
    <Popover v-model:open="open">
        <PopoverTrigger as-child>
            <Button
                type="button"
                variant="outline"
                role="combobox"
                :aria-expanded="open"
                :disabled="disabled"
                class="w-full justify-between font-normal"
                :class="invalid ? 'border-rose-500' : ''"
            >
                <span :class="selected ? 'text-foreground' : 'text-foreground/50'">
                    {{ selected ? selected.label : placeholder }}
                </span>
                <IconChevronDown class="ml-2 size-4 shrink-0 opacity-50" />
            </Button>
        </PopoverTrigger>

        <PopoverContent class="w-(--reka-popover-trigger-width) p-0" align="start">
            <Command>
                <CommandInput :placeholder="searchPlaceholder" />
                <CommandList>
                    <CommandEmpty>{{ emptyText }}</CommandEmpty>
                    <CommandGroup>
                        <CommandItem
                            v-for="option in options"
                            :key="option.value"
                            :value="option.label"
                            @select="select(option)"
                        >
                            {{ option.label }}
                            <IconCheck :class="cn('ml-auto size-4', value === option.value ? 'opacity-100' : 'opacity-0')" />
                        </CommandItem>
                    </CommandGroup>
                </CommandList>
            </Command>
        </PopoverContent>
    </Popover>
</template>
