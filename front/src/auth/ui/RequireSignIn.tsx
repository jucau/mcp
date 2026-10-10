import { useEffect, type ReactNode } from 'react';
import { useAuthorization } from './authorization-context';

export function RequireSignIn({ children }: { children: ReactNode }) {
    const authorization = useAuthorization();
    const signedIn = authorization.isSignedIn();

    useEffect(() => {
        if (!signedIn) {
            void authorization.signIn();
        }
    }, [authorization, signedIn]);

    return signedIn ? children : <p>Redirection vers la connexion…</p>;
}
