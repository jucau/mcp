import { QueryCache, QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { useState } from 'react';
import { Route, Routes } from 'react-router';
import { NotSignedIn } from '../auth/core/model/not-signed-in';
import type { Authorization } from '../auth/core/ports/authorization';
import { AuthorizationProvider } from '../auth/ui/authorization-context';
import { RequireSignIn } from '../auth/ui/RequireSignIn';
import { SignInCallback } from '../auth/ui/SignInCallback';
import type { Library } from '../library/core/ports/library';
import { BooksPage } from '../library/ui/BooksPage';
import { LibraryProvider } from '../library/ui/library-context';

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
                    <main>
                        <h1>Marque-page</h1>
                        <Routes>
                            <Route path="/callback" element={<SignInCallback />} />
                            <Route
                                path="/"
                                element={
                                    <RequireSignIn>
                                        <BooksPage />
                                    </RequireSignIn>
                                }
                            />
                        </Routes>
                    </main>
                </LibraryProvider>
            </AuthorizationProvider>
        </QueryClientProvider>
    );
}
