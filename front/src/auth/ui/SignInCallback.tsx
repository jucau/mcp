import { useEffect, useRef, useState } from 'react';
import { useNavigate } from 'react-router';
import { useAuthorization } from './authorization-context';

export function SignInCallback() {
    const authorization = useAuthorization();
    const navigate = useNavigate();
    const [failure, setFailure] = useState<string>();
    const exchanged = useRef(false);

    useEffect(() => {
        // A code is good for one exchange only, and React runs this effect twice in development.
        if (exchanged.current) {
            return;
        }

        exchanged.current = true;
        authorization.completeSignIn(new URL(window.location.href)).then(
            () => {
                void navigate('/', { replace: true });
            },
            (error: unknown) => {
                setFailure(error instanceof Error ? error.message : String(error));
            },
        );
    }, [authorization, navigate]);

    if (failure !== undefined) {
        return (
            <section role="alert">
                <h2>La connexion a échoué</h2>
                <p>{failure}</p>
                <a href="/">Recommencer</a>
            </section>
        );
    }

    return <p>Connexion en cours…</p>;
}
