import { screen } from '@testing-library/react';
import { NotSignedIn } from '../../src/auth/core/model/not-signed-in';
import { SignedIn } from '../support/auth/signed-in';
import { InMemoryLibrary } from '../support/library/in-memory-library';
import { renderApp } from '../support/render';

describe('the application', () => {
    it('says whose library it is', () => {
        renderApp(new InMemoryLibrary());

        expect(screen.getByRole('heading', { level: 1, name: 'Marque-page' })).toBeInTheDocument();
    });

    it('signs in again when the library says the session is over', async () => {
        const authorization = new SignedIn();
        let signIns = 0;
        authorization.signIn = () => {
            signIns++;

            return Promise.resolve();
        };
        const library = new InMemoryLibrary();
        library.search = () => Promise.reject(new NotSignedIn());

        renderApp(library, authorization);

        await vi.waitFor(() => {
            expect(signIns).toBeGreaterThan(0);
        });
    });
});
