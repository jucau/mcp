import { screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { aBook } from '../../support/library/books';
import { InMemoryLibrary } from '../../support/library/in-memory-library';
import { renderApp } from '../../support/render';

const rows = () => within(screen.getByRole('table')).getAllByRole('row').slice(1);
const titles = () => rows().map((row) => within(row).getByRole('strong').textContent);

describe('the library', () => {
    it('lists the books in columns, with what the reader looks for at a glance', async () => {
        renderApp(
            new InMemoryLibrary(
                aBook({ title: 'Hypérion', author: 'Dan Simmons', readingStatus: 'reading', startedOn: '2026-09-02' }),
                aBook({
                    title: 'Dune',
                    readingStatus: 'read',
                    startedOn: '2025-01-04',
                    finishedOn: '2025-02-10',
                    rating: 5,
                    lentTo: 'Paul',
                    lentOn: '2026-07-03',
                }),
                aBook({ title: 'Le Sorceleur', owned: false }),
            ),
        );

        expect(await screen.findByText('3 livres · triés par titre')).toBeInTheDocument();
        const [dune, hyperion, sorceleur] = rows();
        expect(dune).toHaveTextContent('Lu');
        expect(dune).toHaveTextContent('4 janv. 2025 → 10 févr. 2025');
        expect(within(dune ?? document.body).getByRole('img', { name: '5 sur 5' })).toBeInTheDocument();
        expect(dune).toHaveTextContent('chez Paul, 3 juil. 2026');
        expect(within(hyperion ?? document.body).getByLabelText('En cours')).toBeInTheDocument();
        expect(hyperion).toHaveTextContent('depuis le 2 sept. 2026');
        expect(sorceleur).toHaveTextContent('Envie');
        expect(sorceleur).toHaveTextContent('pas encore à vous');
    });

    it('turns the pages the API serves', async () => {
        const user = userEvent.setup();
        renderApp(
            new InMemoryLibrary(
                ...Array.from({ length: 23 }, (_, index) =>
                    aBook({ title: `Tome ${String(index + 1).padStart(2, '0')}` }),
                ),
            ),
        );

        expect(await screen.findByText('Page 1 sur 2')).toBeInTheDocument();
        expect(rows()).toHaveLength(20);
        expect(screen.getByRole('button', { name: 'Précédente' })).toBeDisabled();

        await user.click(screen.getByRole('button', { name: 'Suivante' }));

        expect(await screen.findByText('Page 2 sur 2')).toBeInTheDocument();
        expect(titles()).toEqual(['Tome 21', 'Tome 22', 'Tome 23']);
        expect(screen.getByRole('button', { name: 'Suivante' })).toBeDisabled();
    });

    it('counts each shelf and shows the one chosen, from its first page', async () => {
        const user = userEvent.setup();
        renderApp(
            new InMemoryLibrary(
                ...Array.from({ length: 21 }, () => aBook({ title: 'Une lecture à venir' })),
                aBook({ title: 'Hypérion', readingStatus: 'reading' }),
            ),
        );
        await user.click(await screen.findByRole('button', { name: 'Suivante' }));

        await user.click(await screen.findByRole('button', { name: 'En cours 1' }));

        expect(screen.getByRole('button', { name: 'En cours 1' })).toHaveAttribute('aria-pressed', 'true');
        expect(screen.getByRole('button', { name: 'Toute la bibliothèque 22' })).toHaveAttribute(
            'aria-pressed',
            'false',
        );
        expect(await screen.findByText('1 livre · triés par titre')).toBeInTheDocument();
        expect(titles()).toEqual(['Hypérion']);
        expect(screen.queryByRole('navigation', { name: 'Pages' })).not.toBeInTheDocument();
    });

    it('narrows the list to the books matching the words typed', async () => {
        const user = userEvent.setup();
        renderApp(
            new InMemoryLibrary(
                aBook({ title: 'Dune', author: 'Frank Herbert' }),
                aBook({ title: 'Fondation', author: 'Isaac Asimov' }),
            ),
        );

        await user.type(await screen.findByRole('searchbox', { name: 'Chercher un livre' }), '  asimov ');

        expect(await screen.findByText('1 livre · triés par titre')).toBeInTheDocument();
        expect(titles()).toEqual(['Fondation']);
    });

    it('says so when nothing matches', async () => {
        const user = userEvent.setup();
        renderApp(new InMemoryLibrary(aBook({ title: 'Dune' })));

        await user.type(await screen.findByRole('searchbox', { name: 'Chercher un livre' }), 'proust');

        expect(await screen.findByText('Aucun livre ne correspond.')).toBeInTheDocument();
    });
});
