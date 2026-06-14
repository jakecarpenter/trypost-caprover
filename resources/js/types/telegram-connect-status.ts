export const TelegramConnectStatus = {
    Unknown: 'unknown',
    Pending: 'pending',
    Connected: 'connected',
} as const;

export type TelegramConnectStatusValue =
    (typeof TelegramConnectStatus)[keyof typeof TelegramConnectStatus];
