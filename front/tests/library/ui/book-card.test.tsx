import { screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { Book } from '../../../src/library/core/model/book';
import { aBook } from '../../support/library/books';
import { InMemoryLibrary } from '../../support/library/in-memory-library';
import { renderApp } from '../../support/render';

const open = async (library: InMemoryLibrary, title: string) => {
    const user = userEvent.setup();
    renderApp(library);
    await user.click(await screen.findByRole('button', { name: new RegExp(title) }));

    return { user, card: () => screen.getByRole('article', { name: `Fiche de ${title}` }) };
};

const row = (title: string) =>
    within(screen.getByRole('table'))
        .getAllByRole('row')
        .find((candidate) => candidate.textContent.includes(title));

describe('the card of a book', () => {
    it('shows everything the library knows about it', async () => {
        const dune: Book = aBook({
            title: 'Dune',
            author: 'Frank Herbert',
            isbn: '9780441172719',
            readingStatus: 'read',
            startedOn: '2025-01-04',
            finishedOn: '2025-02-10',
            rating: 5,
            lentTo: 'Paul',
            lentOn: '2026-07-03',
        });

        const { card } = await open(new InMemoryLibrary(dune), 'Dune');

        expect(card()).toHaveTextContent('ISBN 9780441172719');
        expect(card()).toHaveTextContent('Lu');
        expect(card()).toHaveTextContent('4 janv. 2025');
        expect(card()).toHaveTextContent('10 févr. 2025');
        expect(within(card()).getByRole('img', { name: '5 sur 5' })).toBeInTheDocument();
        expect(card()).toHaveTextContent('à Paul, le 3 juil. 2026');
    });

    it('starts the reading, and the list and its counts follow', async () => {
        const library = new InMemoryLibrary(aBook({ title: 'Hypérion' }));
        const { user, card } = await open(library, 'Hypérion');

        await user.click(within(card()).getByRole('button', { name: 'Commencer' }));

        expect(await within(card()).findByRole('status')).toHaveTextContent('Lecture commencée.');
        expect(card()).toHaveTextContent('En cours');
        expect(await screen.findByRole('button', { name: 'En cours 1' })).toBeInTheDocument();
        expect(within(row('Hypérion') ?? document.body).getByLabelText('En cours')).toBeInTheDocument();
    });

    it('tells, in French, why the library refused, and changes nothing', async () => {
        const library = new InMemoryLibrary(aBook({ title: 'Dune', readingStatus: 'read' }));
        library.refuseNext(
            'reading-transition-not-allowed',
            'Cannot start reading "Dune": its reading status is read.',
        );
        const { user, card } = await open(library, 'Dune');

        await user.click(within(card()).getByRole('button', { name: 'Commencer' }));

        expect(await within(card()).findByRole('status')).toHaveTextContent(
            'Impossible de commencer « Dune » : il est déjà lu.',
        );
        expect(card()).toHaveTextContent('Lu');
    });

    it('lends the book to the borrower typed', async () => {
        const { user, card } = await open(new InMemoryLibrary(aBook({ title: 'Dune' })), 'Dune');

        await user.type(within(card()).getByLabelText('Emprunteur'), 'Marie');
        await user.click(within(card()).getByRole('button', { name: 'Prêter' }));

        expect(await within(card()).findByRole('status')).toHaveTextContent('Prêté à Marie.');
        expect(within(card()).getByLabelText('Emprunteur')).toHaveValue('');
        expect(row('Dune')).toHaveTextContent('chez Marie');
    });

    it('rates the book', async () => {
        const { user, card } = await open(new InMemoryLibrary(aBook({ title: 'Dune', readingStatus: 'read' })), 'Dune');

        await user.click(within(card()).getByRole('button', { name: 'Noter 4 sur 5' }));

        expect(await within(card()).findByRole('status')).toHaveTextContent('Noté 4 sur 5.');
        expect(within(card()).getByRole('img', { name: '4 sur 5' })).toBeInTheDocument();
    });

    it.each([
        ['Terminer', { readingStatus: 'reading' as const }, 'Lecture terminée.'],
        ['Abandonner', { readingStatus: 'reading' as const }, 'Lecture abandonnée.'],
        ['Rendre', { lentTo: 'Paul', lentOn: '2026-07-03' }, 'Rendu : il est de retour à la maison.'],
        ['Acquérir', { owned: false }, 'Il est à vous.'],
    ])('%s says it was done', async (action, state, message) => {
        const { user, card } = await open(new InMemoryLibrary(aBook({ title: 'Dune', ...state })), 'Dune');

        await user.click(within(card()).getByRole('button', { name: action }));

        expect(await within(card()).findByRole('status')).toHaveTextContent(message);
    });

    it('asks before removing a book for good, and can keep it', async () => {
        const library = new InMemoryLibrary(aBook({ title: 'Dune' }));
        const { user, card } = await open(library, 'Dune');

        await user.click(within(card()).getByRole('button', { name: 'Retirer de la bibliothèque' }));
        expect(within(card()).getByRole('alertdialog')).toHaveTextContent('Retirer « Dune » pour de bon');
        await user.click(within(card()).getByRole('button', { name: 'Garder' }));

        expect(within(card()).queryByRole('alertdialog')).not.toBeInTheDocument();
        expect(row('Dune')).toBeDefined();
    });

    it('removes a book for good once confirmed', async () => {
        const { user, card } = await open(
            new InMemoryLibrary(aBook({ title: 'Dune' }), aBook({ title: 'Fondation' })),
            'Dune',
        );

        await user.click(within(card()).getByRole('button', { name: 'Retirer de la bibliothèque' }));
        await user.click(within(card()).getByRole('button', { name: 'Retirer définitivement' }));

        expect(await screen.findByText('Choisissez un livre pour ouvrir sa fiche.')).toBeInTheDocument();
        expect(await screen.findByText('1 livre · triés par titre')).toBeInTheDocument();
    });

    it('says why a book cannot be removed', async () => {
        const library = new InMemoryLibrary(aBook({ title: 'Dune', lentTo: 'Paul', lentOn: '2026-07-03' }));
        library.refuseNext('book-lent');
        const { user, card } = await open(library, 'Dune');

        await user.click(within(card()).getByRole('button', { name: 'Retirer de la bibliothèque' }));
        await user.click(within(card()).getByRole('button', { name: 'Retirer définitivement' }));

        expect(await within(card()).findByRole('status')).toHaveTextContent(
            '« Dune » est prêté à Paul : récupérez-le avant de le retirer.',
        );
    });

    it('opens on nothing until a book is chosen', async () => {
        renderApp(new InMemoryLibrary(aBook()));

        expect(await screen.findByText('Choisissez un livre pour ouvrir sa fiche.')).toBeInTheDocument();
    });
});
