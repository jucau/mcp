import { createContext, use, type ReactNode } from 'react';
import type { Authorization } from '../core/ports/authorization';

const AuthorizationContext = createContext<Authorization | null>(null);

export function AuthorizationProvider({
    authorization,
    children,
}: {
    authorization: Authorization;
    children: ReactNode;
}) {
    return <AuthorizationContext value={authorization}>{children}</AuthorizationContext>;
}

export function useAuthorization(): Authorization {
    const authorization = use(AuthorizationContext);

    if (authorization === null) {
        throw new Error('useAuthorization() needs an <AuthorizationProvider> above it.');
    }

    return authorization;
}
