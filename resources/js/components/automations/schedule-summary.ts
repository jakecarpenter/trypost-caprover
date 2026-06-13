import { trans, transChoice } from 'laravel-vue-i18n';

import type { ScheduleData } from '@/types/automation/schedule-data';
import { ScheduleField } from '@/types/automation/schedule-field';
import { TriggerType } from '@/types/automation/trigger-type';

const pad2 = (n: number) => String(n).padStart(2, '0');
const clamp = (n: number, min: number, max: number) => Math.min(Math.max(n, min), max);
const num = (data: ScheduleData, key: keyof ScheduleData, fallback: number, min: number, max: number) =>
    clamp(Number(data[key]) || fallback, min, max);

const summaryKey = (key: string) => `automations.config.trigger.schedule.summary.${key}`;
const weekdayKey = (label: string) => `automations.config.trigger.schedule.weekday_names.${label}`;

const weekdayLabels: Record<number, string> = {
    0: 'sun', 1: 'mon', 2: 'tue', 3: 'wed', 4: 'thu', 5: 'fri', 6: 'sat',
};

/**
 * Reverse-engineer the schedule_* fields from a raw cron string. Covers the
 * exact shapes our UI generates (minutes/hours/days/weeks/months presets).
 * Returns null when the cron doesn't match any known pattern.
 */
const inferScheduleFromCron = (cron: string): Partial<ScheduleData> | null => {
    const parts = cron.trim().split(/\s+/);
    if (parts.length !== 5) return null;

    const [minute, hour, dom, month, dow] = parts;
    const intervalOf = (token: string): number | null =>
        token.startsWith('*/') && /^\d+$/.test(token.slice(2)) ? Number(token.slice(2)) : null;
    const isNum = (t: string) => /^\d+$/.test(t);

    if (minute === '*' && hour === '*' && dom === '*' && month === '*' && dow === '*') {
        return { schedule_field: ScheduleField.Minutes, schedule_minutes_interval: 1 };
    }
    const m = intervalOf(minute);
    if (m !== null && hour === '*' && dom === '*' && month === '*' && dow === '*') {
        return { schedule_field: ScheduleField.Minutes, schedule_minutes_interval: m };
    }
    const h = intervalOf(hour);
    if (isNum(minute) && h !== null && dom === '*' && month === '*' && dow === '*') {
        return { schedule_field: ScheduleField.Hours, schedule_hours_interval: h, schedule_minute: Number(minute) };
    }
    const d = intervalOf(dom);
    if (isNum(minute) && isNum(hour) && d !== null && month === '*' && dow === '*') {
        return { schedule_field: ScheduleField.Days, schedule_days_interval: d, schedule_hour: Number(hour), schedule_minute: Number(minute) };
    }
    if (isNum(minute) && isNum(hour) && dom === '*' && month === '*' && dow === '*') {
        return { schedule_field: ScheduleField.Days, schedule_days_interval: 1, schedule_hour: Number(hour), schedule_minute: Number(minute) };
    }
    if (isNum(minute) && isNum(hour) && dom === '*' && month === '*' && /^\d+(,\d+)*$/.test(dow)) {
        return { schedule_field: ScheduleField.Weeks, schedule_weekdays: dow.split(',').map(Number), schedule_hour: Number(hour), schedule_minute: Number(minute) };
    }
    if (isNum(minute) && isNum(hour) && isNum(dom) && month === '*' && dow === '*') {
        return { schedule_field: ScheduleField.Months, schedule_day_of_month: Number(dom), schedule_hour: Number(hour), schedule_minute: Number(minute) };
    }
    return null;
};

/**
 * Returns `data` with every schedule_* field populated. Single source of truth
 * used by both the Trigger card (canvas) and the config sidebar — guarantees
 * `humanSchedule` and the `<TriggerNodeConfig>` initial state never diverge.
 *
 * Order of resolution per field: explicit value in `data` → inferred from
 * `data.cron` → hardcoded fallback.
 */
export const normalizeScheduleData = (data: ScheduleData): ScheduleData => {
    const inferred = (data.schedule_field === undefined && typeof data.cron === 'string')
        ? inferScheduleFromCron(data.cron) ?? {}
        : {};

    return {
        ...data,
        schedule_field: data.schedule_field ?? inferred.schedule_field ?? ScheduleField.Days,
        schedule_minutes_interval: data.schedule_minutes_interval ?? inferred.schedule_minutes_interval ?? 5,
        schedule_hours_interval: data.schedule_hours_interval ?? inferred.schedule_hours_interval ?? 1,
        schedule_days_interval: data.schedule_days_interval ?? inferred.schedule_days_interval ?? 1,
        schedule_hour: data.schedule_hour ?? inferred.schedule_hour ?? 9,
        schedule_minute: data.schedule_minute ?? inferred.schedule_minute ?? 0,
        schedule_weekdays: data.schedule_weekdays ?? inferred.schedule_weekdays ?? [1],
        schedule_day_of_month: data.schedule_day_of_month ?? inferred.schedule_day_of_month ?? 1,
    };
};

export const generateScheduleCron = (data: ScheduleData): string => {
    const minute = num(data, 'schedule_minute', 0, 0, 59);
    const hour = num(data, 'schedule_hour', 9, 0, 23);

    switch (data.schedule_field) {
        case ScheduleField.Minutes: {
            const n = num(data, 'schedule_minutes_interval', 5, 1, 59);
            return `*/${n} * * * *`;
        }
        case ScheduleField.Hours: {
            const n = num(data, 'schedule_hours_interval', 1, 1, 23);
            return `${minute} */${n} * * *`;
        }
        case ScheduleField.Days: {
            const n = num(data, 'schedule_days_interval', 1, 1, 31);
            return `${minute} ${hour} */${n} * *`;
        }
        case ScheduleField.Weeks: {
            const days = [...(data.schedule_weekdays ?? [])].sort((a, b) => a - b);
            return `${minute} ${hour} * * ${days.length ? days.join(',') : '1'}`;
        }
        case ScheduleField.Months: {
            const d = num(data, 'schedule_day_of_month', 1, 1, 31);
            return `${minute} ${hour} ${d} * *`;
        }
    }
    return '0 9 * * *';
};

export const humanSchedule = (data: ScheduleData): string => {
    const minute = num(data, 'schedule_minute', 0, 0, 59);
    const hour = num(data, 'schedule_hour', 9, 0, 23);
    const time = `${pad2(hour)}:${pad2(minute)}`;

    switch (data.schedule_field) {
        case ScheduleField.Minutes: {
            const n = num(data, 'schedule_minutes_interval', 5, 1, 59);
            return transChoice(summaryKey('every_n_minutes'), n);
        }
        case ScheduleField.Hours: {
            const n = num(data, 'schedule_hours_interval', 1, 1, 23);
            return transChoice(summaryKey('every_n_hours'), n, { minute: pad2(minute) });
        }
        case ScheduleField.Days: {
            const n = num(data, 'schedule_days_interval', 1, 1, 31);
            return transChoice(summaryKey('every_n_days'), n, { time });
        }
        case ScheduleField.Weeks: {
            const days = [...(data.schedule_weekdays ?? [])].sort((a, b) => a - b);
            const labels = days.map((v) => trans(weekdayKey(weekdayLabels[v]))).join(', ');
            return trans(summaryKey('weekly'), { days: labels, time });
        }
        case ScheduleField.Months: {
            const d = num(data, 'schedule_day_of_month', 1, 1, 31);
            return trans(summaryKey('monthly'), { day: String(d), time });
        }
    }
    return '';
};

export const triggerSummary = (data: ScheduleData): string => {
    switch (data.trigger_type) {
        case TriggerType.Schedule:
            return humanSchedule(normalizeScheduleData(data));
        case TriggerType.PostPublished:
            return trans('automations.config.trigger.types.post_published');
        case TriggerType.PostScheduled:
            return trans('automations.config.trigger.types.post_scheduled');
    }
    return '';
};
