import type { Permissions } from '@type/permission';

import { page } from '@inertiajs/svelte';

export type { Permissions };

let permissions = $state([]);

export const setPermissions = (_permissions: Permissions[]) => (permissions = _permissions);

/**
 * Check if user has specified permission(s), supporting wildcard patterns
 * @param _permission Single permission string (with optional wildcard) or array of permissions
 * @returns boolean indicating if user has any of the specified permissions
 * @throws Error if permissions are not properly configured or pattern is invalid
 */
export const can = (_permission: Permissions | Permissions[]): boolean => {
    const _permissions = (page?.props?.auth?.permissions ?? permissions ?? []) as Permissions[];
    if (!_permissions?.length) return;

    // Helper function to check if a permission matches a wildcard pattern
    const matchesWildcard = (perm: Permissions, pattern: Permissions): boolean => {
        if (!pattern) {
            console.warn('Invalid permission provided');

            return false;
        }
        if (!pattern.includes('*')) {
            return perm === pattern;
        }
        try {
            const regex = new RegExp(`^${pattern.replace(/\*/g, '.*')}$`);

            return regex.test(perm);
        } catch (e) {
            console.warn(`Invalid wildcard pattern: ${pattern}`);

            return false;
        }
    };

    if (!Array.isArray(_permission)) {
        return _permissions.some((p) => matchesWildcard(p, _permission));
    }

    return _permission.some((p) => _permissions.some((perm) => matchesWildcard(perm, p)));
};
