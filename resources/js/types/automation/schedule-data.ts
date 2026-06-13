import type { ScheduleFieldValue } from './schedule-field';
import type { TriggerTypeValue } from './trigger-type';

/**
 * Shape of the `data` blob persisted on a Trigger node. Most fields are
 * conditional on `trigger_type`; the schedule_* fields are only meaningful
 * when `trigger_type === 'schedule'`.
 */
export interface ScheduleData {
    trigger_type?: TriggerTypeValue;
    cron?: string;
    poll_interval?: number;
    feed_url?: string;
    url?: string;
    items_path?: string;
    item_key_path?: string;
    item_date_path?: string;
    schedule_field?: ScheduleFieldValue;
    schedule_minutes_interval?: number;
    schedule_hours_interval?: number;
    schedule_days_interval?: number;
    schedule_hour?: number;
    schedule_minute?: number;
    schedule_weekdays?: number[];
    schedule_day_of_month?: number;
    schedule_timezone?: string;
}
