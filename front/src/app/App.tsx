import { QueryCache, QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { useState } from 'react';
import { Route, Routes } from 'react-router';
import { NotSignedIn } from '../auth/core/model/not-signed-in';
import type { Authorization } from '../auth/core/ports/authorization';
import { AuthorizationProvider } from '../auth/ui/authorization-context';
import { RequireSignIn } from '../auth/ui/RequireSignIn';
import { SignInCallback } from '../auth/ui/SignInCallback';
import type { Library } from '../library/core/ports/library';
import { LibraryPage } from '../library/ui/LibraryPage';
import { LibraryProvider } from '../library/ui/library-context';
import styles from './App.module.css';

export interface Adapters {
    readonly authorization: Authorization;
    readonly library: Library;
}

export function App({ authorization, library }: Adapters) {
    const [queryClient] = useState(
        () =>
            new QueryClient({
                queryCache: new QueryCache({
                    onError: (error) => {
                        if (error instanceof NotSignedIn) {
                            void authorization.signIn();
                        }
                    },
                }),
                defaultOptions: {
                    queries: { retry: (failures, error) => !(error instanceof NotSignedIn) && failures < 3 },
                },
            }),
    );

    return (
        <QueryClientProvider client={queryClient}>
            <AuthorizationProvider authorization={authorization}>
                <LibraryProvider library={library}>
                    <div className={styles.page}>
                        <header className={styles.bar}>
                            <span className={styles.mark} aria-hidden="true" />
                            <h1 className={styles.brand}>Marque-page</h1>
                        </header>
                        <Routes>
                            <Route path="/callback" element={<SignInCallback />} />
                            <Route
                                path="/"
                                element={
                                    <RequireSignIn>
                                        <LibraryPage />
                                    </RequireSignIn>
                                }
                            />
                        </Routes>
                    </div>
                </LibraryProvider>
            </AuthorizationProvider>
        </QueryClientProvider>
    );
}
