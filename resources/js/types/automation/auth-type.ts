/**
 * Auth strategies for the HTTP Request node. Mirrors the backend
 * App\Enums\Automation\AuthType enum.
 */
export const AuthType = {
    None: 'none',
    Bearer: 'bearer',
    Basic: 'basic',
    ApiKey: 'api_key',
} as const;

export type AuthTypeValue = (typeof AuthType)[keyof typeof AuthType];
