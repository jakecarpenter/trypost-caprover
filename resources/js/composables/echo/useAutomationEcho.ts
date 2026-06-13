import { useEcho } from '@laravel/echo-vue';

export const useAutomationEcho = <T = unknown>(
    automationId: string,
    event: string | string[],
    callback: (payload: T) => void,
) => {
    return useEcho<T>(`automation.${automationId}`, event, callback);
};
