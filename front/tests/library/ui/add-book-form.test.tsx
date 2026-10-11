import { screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { aBook } from '../../support/library/books';
import { InMemoryLibrary } from '../../support/library/in-memory-library';
import { renderApp } from '../../support/render';

const openTheForm = async (library: InMemoryLibrary) => {
    const user = userEvent.setup();
    renderApp(library);
    await user.click(await screen.findByRole('button', { name: 'Ajouter un livre' }));
    const form = screen.getByRole('form', { name: 'Ajouter un livre' });

    return { user, form };
};

describe('adding a book from the front', () => {
    it('adds the book, opens its card and counts it', async () => {
        const { user, form } = await openTheForm(new InMemoryLibrary(aBook({ title: 'Fondation' })));

        await user.type(within(form).getByLabelText('Titre'), 'Dune');
        await user.type(within(form).getByLabelText('Auteur'), 'Frank Herbert');
        await user.type(within(form).getByLabelText('ISBN (facultatif)'), '9780441172719');
        await user.click(within(form).getByRole('button', { name: 'Ajouter' }));

        const card = await screen.findByRole('article', { name: 'Fiche du livre « Dune »' });
        expect(card).toHaveTextContent('ISBN 9780441172719');
        expect(await screen.findByText('2 livres · triés par titre')).toBeInTheDocument();
        expect(screen.queryByRole('form', { name: 'Ajouter un livre' })).not.toBeInTheDocument();
    });

    it('puts a book wished for on the wishlist', async () => {
        const { user, form } = await openTheForm(new InMemoryLibrary());

        await user.type(within(form).getByLabelText('Titre'), 'Le Sorceleur');
        await user.type(within(form).getByLabelText('Auteur'), 'Andrzej Sapkowski');
        await user.click(within(form).getByLabelText("va sur ma liste d'envies"));
        await user.click(within(form).getByRole('button', { name: 'Ajouter' }));

        expect(await screen.findByRole('article', { name: 'Fiche du livre « Le Sorceleur »' })).toHaveTextContent(
            "liste d'envies",
        );
        expect(await screen.findByRole('button', { name: "Liste d'envies 1" })).toBeInTheDocument();
    });

    it('tells why the library refused, and keeps what was typed', async () => {
        const library = new InMemoryLibrary();
        library.refuseNext('duplicate-isbn');
        const { user, form } = await openTheForm(library);

        await user.type(within(form).getByLabelText('Titre'), 'Dune');
        await user.type(within(form).getByLabelText('Auteur'), 'Frank Herbert');
        await user.type(within(form).getByLabelText('ISBN (facultatif)'), '9780441172719');
        await user.click(within(form).getByRole('button', { name: 'Ajouter' }));

        expect(await within(form).findByRole('status')).toHaveTextContent(
            "Un livre avec l'ISBN 9780441172719 est déjà dans la bibliothèque",
        );
        expect(within(form).getByLabelText('Titre')).toHaveValue('Dune');
    });

    it('asks for a title and an author before calling the library', async () => {
        const { user, form } = await openTheForm(new InMemoryLibrary());

        await user.click(within(form).getByRole('button', { name: 'Ajouter' }));

        expect(within(form).getByLabelText('Titre')).toBeInvalid();
        expect(within(form).getByLabelText('Auteur')).toBeInvalid();
    });

    it('can be left without adding anything', async () => {
        const { user, form } = await openTheForm(new InMemoryLibrary());

        await user.click(within(form).getByRole('button', { name: 'Annuler' }));

        expect(screen.getByText('Choisissez un livre pour ouvrir sa fiche.')).toBeInTheDocument();
    });
});
