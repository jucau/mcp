import { render, screen } from '@testing-library/react';
import { MemoryRouter } from 'react-router';
import { App } from '../../src/app/App';
import { NotSignedIn } from '../../src/auth/core/model/not-signed-in';
import { SignedIn } from '../support/auth/signed-in';
import { aBook } from '../support/library/books';
import { InMemoryLibrary } from '../support/library/in-memory-library';

describe('the application', () => {
    it('shows the books of the reader once signed in', async () => {
        render(
            <MemoryRouter>
                <App
                    authorization={new SignedIn()}
                    library={
                        new InMemoryLibrary(
                            aBook({ title: 'Dune', author: 'Frank Herbert' }),
                            aBook({ title: 'Fondation', author: 'Isaac Asimov' }),
                        )
                    }
                />
            </MemoryRouter>,
        );

        expect(screen.getByRole('heading', { level: 1, name: 'Marque-page' })).toBeInTheDocument();
        expect(await screen.findByRole('heading', { name: '2 livres' })).toBeInTheDocument();
        expect(screen.getAllByRole('listitem').map((item) => item.textContent)).toEqual([
            'Dune — Frank Herbert',
            'Fondation — Isaac Asimov',
        ]);
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

        render(
            <MemoryRouter>
                <App authorization={authorization} library={library} />
            </MemoryRouter>,
        );

        await vi.waitFor(() => {
            expect(signIns).toBe(1);
        });
    });
});
