export const permissions = ['*'] as const;

export const wildcardPermissions = ['*'] as const;

export type BasePermissions = (typeof permissions)[number];

export type WildcardPermissions = (typeof wildcardPermissions)[number];

export type Permissions = BasePermissions | WildcardPermissions;
