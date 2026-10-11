import { screen, waitFor, within } from '@testing-library/react';
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

    it('fills the title and the author from the ISBN, then adds the book', async () => {
        const library = new InMemoryLibrary();
        library.knows({ isbn: '9780441172719', title: 'Dune', author: 'Frank Herbert', publishedIn: 1965, pages: 896 });
        const { user, form } = await openTheForm(library);

        await user.type(within(form).getByLabelText('ISBN (facultatif)'), ' 9780441172719 ');
        await user.click(within(form).getByRole('button', { name: "Compléter depuis l'ISBN" }));

        await waitFor(() => {
            expect(within(form).getByLabelText('Titre')).toHaveValue('Dune');
        });
        expect(within(form).getByLabelText('Auteur')).toHaveValue('Frank Herbert');
        expect(within(form).getByLabelText('ISBN (facultatif)')).toHaveValue('9780441172719');

        await user.click(within(form).getByRole('button', { name: 'Ajouter' }));

        expect(await screen.findByRole('article', { name: 'Fiche du livre « Dune »' })).toHaveTextContent(
            'ISBN 9780441172719',
        );
    });

    it('tells why the catalog gave nothing, and keeps what was typed', async () => {
        const { user, form } = await openTheForm(new InMemoryLibrary());

        await user.type(within(form).getByLabelText('Titre'), 'Dune');
        await user.type(within(form).getByLabelText('ISBN (facultatif)'), '9780441172719');
        await user.click(within(form).getByRole('button', { name: "Compléter depuis l'ISBN" }));

        expect(await within(form).findByRole('status')).toHaveTextContent(
            "Le catalogue ne connaît pas l'ISBN 9780441172719 : saisissez le titre et l'auteur.",
        );
        expect(within(form).getByLabelText('Titre')).toHaveValue('Dune');
    });

    it('forgets the last refusal when the catalog is asked again', async () => {
        const library = new InMemoryLibrary();
        library.knows({ isbn: '9780441172719', title: 'Dune', author: null, publishedIn: null, pages: null });
        library.refuseNext('catalog-unavailable');
        const { user, form } = await openTheForm(library);

        await user.type(within(form).getByLabelText('Auteur'), 'herbert');
        await user.type(within(form).getByLabelText('ISBN (facultatif)'), '9780441172719');
        await user.click(within(form).getByRole('button', { name: "Compléter depuis l'ISBN" }));
        expect(await within(form).findByRole('status')).toHaveTextContent('Le catalogue ne répond pas');

        await user.click(within(form).getByRole('button', { name: "Compléter depuis l'ISBN" }));

        await waitFor(() => {
            expect(within(form).getByLabelText('Titre')).toHaveValue('Dune');
        });
        expect(within(form).getByLabelText('Auteur')).toHaveValue('herbert');
        expect(within(form).queryByRole('status')).not.toBeInTheDocument();
    });

    it('has nothing to look up without an ISBN', async () => {
        const { user, form } = await openTheForm(new InMemoryLibrary());

        expect(within(form).getByRole('button', { name: "Compléter depuis l'ISBN" })).toBeDisabled();

        await user.type(within(form).getByLabelText('ISBN (facultatif)'), '   ');

        expect(within(form).getByRole('button', { name: "Compléter depuis l'ISBN" })).toBeDisabled();
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
