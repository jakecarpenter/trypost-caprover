<script setup lang="ts">
import { computed, ref, watch } from 'vue';

import {
    generateScheduleCron,
    humanSchedule as scheduleSummary,
    normalizeScheduleData,
} from '@/components/automations/schedule-summary';
import InputError from '@/components/InputError.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import date from '@/date';
import type { ScheduleData } from '@/types/automation/schedule-data';
import { ScheduleField } from '@/types/automation/schedule-field';
import { TriggerType, type TriggerTypeValue } from '@/types/automation/trigger-type';

const props = defineProps<{
    data: Record<string, unknown>;
    errors?: Record<string, string>;
}>();
const emit = defineEmits<{ update: [Record<string, unknown>] }>();

const pad2 = (n: number) => String(n).padStart(2, '0');
const clamp = (n: number, min: number, max: number) => Math.min(Math.max(n, min), max);
const snapToFiveMinutes = (n: number) => (Math.round(n / 5) * 5) % 60;

const timezoneAbbr = date.getTimezoneAbbr();

// Single source of truth for default + inferred field values, shared with the
// Trigger card via `triggerSummary`. Anything beyond `normalizeScheduleData`'s
// scope (trigger_type, cron, timezone) is filled in here.
const local = ref<ScheduleData & { trigger_type: TriggerTypeValue; cron: string; schedule_timezone: string }>({
    ...normalizeScheduleData(props.data as ScheduleData),
    trigger_type: (props.data.trigger_type as TriggerTypeValue) ?? TriggerType.Schedule,
    cron: (props.data.cron as string) ?? '0 9 * * *',
    schedule_minute: snapToFiveMinutes(Number(props.data.schedule_minute ?? normalizeScheduleData(props.data as ScheduleData).schedule_minute) || 0),
    schedule_timezone: (props.data.schedule_timezone as string) ?? date.getUserTimezone(),
});

const num = (key: keyof typeof local.value, fallback: number, min: number, max: number) =>
    clamp(Number(local.value[key]) || fallback, min, max);

const proxy = (key: 'schedule_hour' | 'schedule_minute', max: number) => computed({
    get: () => pad2(num(key, 0, 0, max)),
    set: (v: string) => { local.value[key] = Number(v) || 0; },
});

const scheduleHourStr = proxy('schedule_hour', 23);
const scheduleMinuteStr = proxy('schedule_minute', 59);

const hourOptions = Array.from({ length: 24 }, (_, i) => pad2(i));
const minuteOptions = Array.from({ length: 12 }, (_, i) => pad2(i * 5));

const weekdays = [
    { value: 1, label: 'mon' },
    { value: 2, label: 'tue' },
    { value: 3, label: 'wed' },
    { value: 4, label: 'thu' },
    { value: 5, label: 'fri' },
    { value: 6, label: 'sat' },
    { value: 0, label: 'sun' },
] as const;

const toggleWeekday = (value: number) => {
    const set = new Set(local.value.schedule_weekdays ?? []);
    if (set.has(value)) {
        set.delete(value);
    } else {
        set.add(value);
    }
    local.value.schedule_weekdays = Array.from(set);
};

const isWeekdaySelected = (value: number) =>
    (local.value.schedule_weekdays ?? []).includes(value);

const generatedCron = computed(() => generateScheduleCron(local.value));
const humanSchedule = computed(() => scheduleSummary(local.value));

// Append the user's timezone only for schedules pinned to a clock time
// (days/weeks/months). Minute/hour intervals are relative, so a timezone
// would be meaningless next to them.
const scheduleSummaryLine = computed(() => {
    if (!humanSchedule.value) {
        return '';
    }
    const pinsClockTime = local.value.schedule_field === ScheduleField.Days
        || local.value.schedule_field === ScheduleField.Weeks
        || local.value.schedule_field === ScheduleField.Months;

    return pinsClockTime
        ? `${humanSchedule.value} (${timezoneAbbr})`
        : humanSchedule.value;
});

watch(generatedCron, (cron) => {
    if (local.value.trigger_type === TriggerType.Schedule) {
        local.value.cron = cron;
        local.value.schedule_timezone = date.getUserTimezone();
    }
}, { immediate: true });

watch(local, (val) => emit('update', val), { deep: true });
</script>

<template>
    <div class="space-y-3">
        <div>
            <Label class="mb-1 block">{{ $t('automations.config.trigger.type') }}</Label>
            <Select v-model="local.trigger_type">
                <SelectTrigger class="w-full">
                    <SelectValue :placeholder="$t('automations.config.select_placeholder')" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem :value="TriggerType.Schedule">{{ $t('automations.config.trigger.types.schedule') }}</SelectItem>
                    <SelectItem :value="TriggerType.PostPublished">{{ $t('automations.config.trigger.types.post_published') }}</SelectItem>
                    <SelectItem :value="TriggerType.PostScheduled">{{ $t('automations.config.trigger.types.post_scheduled') }}</SelectItem>
                </SelectContent>
            </Select>
            <InputError :message="errors?.trigger_type" class="mt-1" />
        </div>

        <template v-if="local.trigger_type === TriggerType.PostPublished">
            <p class="rounded-md bg-muted px-3 py-2 text-xs text-foreground/70">
                {{ $t('automations.config.trigger.post_published_hint') }}
            </p>
        </template>

        <template v-if="local.trigger_type === TriggerType.PostScheduled">
            <p class="rounded-md bg-muted px-3 py-2 text-xs text-foreground/70">
                {{ $t('automations.config.trigger.post_scheduled_hint') }}
            </p>
        </template>

        <template v-if="local.trigger_type === TriggerType.Schedule">
            <div>
                <Label class="mb-1 block">{{ $t('automations.config.trigger.schedule.field') }}</Label>
                <Select v-model="local.schedule_field">
                    <SelectTrigger class="w-full">
                        <SelectValue :placeholder="$t('automations.config.select_placeholder')" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem :value="ScheduleField.Minutes">{{ $t('automations.config.trigger.schedule.fields.minutes') }}</SelectItem>
                        <SelectItem :value="ScheduleField.Hours">{{ $t('automations.config.trigger.schedule.fields.hours') }}</SelectItem>
                        <SelectItem :value="ScheduleField.Days">{{ $t('automations.config.trigger.schedule.fields.days') }}</SelectItem>
                        <SelectItem :value="ScheduleField.Weeks">{{ $t('automations.config.trigger.schedule.fields.weeks') }}</SelectItem>
                        <SelectItem :value="ScheduleField.Months">{{ $t('automations.config.trigger.schedule.fields.months') }}</SelectItem>
                    </SelectContent>
                </Select>
            </div>

            <div v-if="local.schedule_field === ScheduleField.Minutes">
                <Label class="mb-1 block">{{ $t('automations.config.trigger.schedule.minutes_interval') }}</Label>
                <Input type="number" v-model.number="local.schedule_minutes_interval" min="1" max="59" />
            </div>

            <template v-if="local.schedule_field === ScheduleField.Hours">
                <div>
                    <Label class="mb-1 block">{{ $t('automations.config.trigger.schedule.hours_interval') }}</Label>
                    <Input type="number" v-model.number="local.schedule_hours_interval" min="1" max="23" />
                </div>
                <div>
                    <Label class="mb-1 block">{{ $t('automations.config.trigger.schedule.minute') }}</Label>
                    <Select v-model="scheduleMinuteStr">
                        <SelectTrigger class="w-full">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem v-for="m in minuteOptions" :key="m" :value="m">{{ m }}</SelectItem>
                        </SelectContent>
                    </Select>
                </div>
            </template>

            <template v-if="local.schedule_field === ScheduleField.Days">
                <div>
                    <Label class="mb-1 block">{{ $t('automations.config.trigger.schedule.days_interval') }}</Label>
                    <Input type="number" v-model.number="local.schedule_days_interval" min="1" max="31" />
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <Label class="mb-1 block">{{ $t('automations.config.trigger.schedule.hour') }}</Label>
                        <Select v-model="scheduleHourStr">
                            <SelectTrigger class="w-full">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="h in hourOptions" :key="h" :value="h">{{ h }}</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div>
                        <Label class="mb-1 block">{{ $t('automations.config.trigger.schedule.minute') }}</Label>
                        <Select v-model="scheduleMinuteStr">
                            <SelectTrigger class="w-full">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="m in minuteOptions" :key="m" :value="m">{{ m }}</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                </div>
            </template>

            <template v-if="local.schedule_field === ScheduleField.Weeks">
                <div>
                    <Label class="mb-1 block">{{ $t('automations.config.trigger.schedule.weekdays') }}</Label>
                    <div class="flex flex-wrap gap-1.5">
                        <button
                            v-for="day in weekdays"
                            :key="day.value"
                            type="button"
                            class="rounded-md border-2 px-3 py-1 text-xs font-semibold transition-colors"
                            :class="isWeekdaySelected(day.value)
                                ? 'border-foreground bg-amber-200 text-foreground'
                                : 'border-foreground/15 bg-card text-foreground/70 hover:border-foreground/30'"
                            @click="toggleWeekday(day.value)"
                        >
                            {{ $t(`automations.config.trigger.schedule.weekday_names.${day.label}`) }}
                        </button>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <Label class="mb-1 block">{{ $t('automations.config.trigger.schedule.hour') }}</Label>
                        <Select v-model="scheduleHourStr">
                            <SelectTrigger class="w-full">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="h in hourOptions" :key="h" :value="h">{{ h }}</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div>
                        <Label class="mb-1 block">{{ $t('automations.config.trigger.schedule.minute') }}</Label>
                        <Select v-model="scheduleMinuteStr">
                            <SelectTrigger class="w-full">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="m in minuteOptions" :key="m" :value="m">{{ m }}</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                </div>
            </template>

            <template v-if="local.schedule_field === ScheduleField.Months">
                <div>
                    <Label class="mb-1 block">{{ $t('automations.config.trigger.schedule.day_of_month') }}</Label>
                    <Input type="number" v-model.number="local.schedule_day_of_month" min="1" max="31" />
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <Label class="mb-1 block">{{ $t('automations.config.trigger.schedule.hour') }}</Label>
                        <Select v-model="scheduleHourStr">
                            <SelectTrigger class="w-full">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="h in hourOptions" :key="h" :value="h">{{ h }}</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div>
                        <Label class="mb-1 block">{{ $t('automations.config.trigger.schedule.minute') }}</Label>
                        <Select v-model="scheduleMinuteStr">
                            <SelectTrigger class="w-full">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="m in minuteOptions" :key="m" :value="m">{{ m }}</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                </div>
            </template>

            <p class="rounded-md bg-muted px-3 py-2 text-xs text-foreground/70">{{ scheduleSummaryLine }}</p>
        </template>
    </div>
</template>
