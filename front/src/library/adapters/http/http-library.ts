import createClient, { type Client, type Middleware } from 'openapi-fetch';
import { NotSignedIn } from '../../../auth/core/model/not-signed-in';
import type { Authorization } from '../../../auth/core/ports/authorization';
import type { Book } from '../../core/model/book';
import type { BookSearch } from '../../core/model/book-search';
import type { Page } from '../../core/model/page';
import type { Outcome } from '../../core/model/refusal';
import type { Library } from '../../core/ports/library';
import type { components, paths } from './contract';

type BookResource = components['schemas']['BookResource'];

export class HttpLibrary implements Library {
    private readonly client: Client<paths>;

    constructor(apiUrl: URL, authorization: Authorization) {
        this.client = createClient<paths>({ baseUrl: apiUrl.href });
        this.client.use(bearer(authorization));
    }

    async search(criteria: BookSearch): Promise<Page<Book>> {
        const { data, response } = await this.client.GET('/books', {
            params: {
                query: {
                    ...(criteria.text === undefined || criteria.text === '' ? {} : { query: criteria.text }),
                    ...(criteria.readingStatus === undefined ? {} : { reading_status: criteria.readingStatus }),
                    ...(criteria.owned === undefined ? {} : { owned: flag(criteria.owned) }),
                    ...(criteria.lent === undefined ? {} : { lent: flag(criteria.lent) }),
                    ...(criteria.page === undefined ? {} : { page: criteria.page }),
                    ...(criteria.perPage === undefined ? {} : { per_page: criteria.perPage }),
                },
            },
        });

        if (data === undefined) {
            throw failure(response);
        }

        return {
            items: data.data.map(toBook),
            page: data.meta.current_page,
            perPage: data.meta.per_page,
            total: data.meta.total,
        };
    }

    startReading(bookId: string): Promise<Outcome<Book>> {
        return this.act(this.client.POST('/books/{book}/start-reading', { params: { path: { book: bookId } } }));
    }

    finishReading(bookId: string): Promise<Outcome<Book>> {
        return this.act(this.client.POST('/books/{book}/finish-reading', { params: { path: { book: bookId } } }));
    }

    abandonReading(bookId: string): Promise<Outcome<Book>> {
        return this.act(this.client.POST('/books/{book}/abandon-reading', { params: { path: { book: bookId } } }));
    }

    rate(bookId: string, rating: number): Promise<Outcome<Book>> {
        return this.act(
            this.client.POST('/books/{book}/rate', { params: { path: { book: bookId } }, body: { rating } }),
        );
    }

    lend(bookId: string, borrower: string): Promise<Outcome<Book>> {
        return this.act(
            this.client.POST('/books/{book}/lend', { params: { path: { book: bookId } }, body: { borrower } }),
        );
    }

    returnBook(bookId: string): Promise<Outcome<Book>> {
        return this.act(this.client.POST('/books/{book}/return', { params: { path: { book: bookId } } }));
    }

    acquire(bookId: string): Promise<Outcome<Book>> {
        return this.act(this.client.POST('/books/{book}/acquire', { params: { path: { book: bookId } } }));
    }

    async remove(bookId: string): Promise<Outcome<null>> {
        const { error, response } = await this.client.DELETE('/books/{book}', { params: { path: { book: bookId } } });

        return response.ok ? { ok: true, value: null } : refusal(error, response);
    }

    private async act(
        call: Promise<{ data?: { data: BookResource }; error?: unknown; response: Response }>,
    ): Promise<Outcome<Book>> {
        const { data, error, response } = await call;

        return data === undefined ? refusal(error, response) : { ok: true, value: toBook(data.data) };
    }
}

const bearer = (authorization: Authorization): Middleware => ({
    async onRequest({ request }) {
        const token = await authorization.accessToken();

        if (token === undefined) {
            throw new NotSignedIn();
        }

        request.headers.set('Authorization', `Bearer ${token}`);

        return request;
    },
});

const refusal = (problem: unknown, response: Response): { ok: false; refusal: { type: string; detail: string } } => {
    if (![404, 409, 422].includes(response.status) || !isProblem(problem)) {
        throw failure(response);
    }

    return { ok: false, refusal: { type: problem.type.split('/').pop() ?? problem.type, detail: problem.detail } };
};

const isProblem = (problem: unknown): problem is { type: string; detail: string } =>
    typeof problem === 'object' &&
    problem !== null &&
    'type' in problem &&
    'detail' in problem &&
    typeof problem.type === 'string' &&
    typeof problem.detail === 'string';

const flag = (value: boolean): 'true' | 'false' => (value ? 'true' : 'false');

const failure = (response: Response): Error =>
    response.status === 401 ? new NotSignedIn() : new Error(`The API answered ${String(response.status)}.`);

const toBook = (book: BookResource): Book => ({
    id: book.id,
    title: book.title,
    author: book.author,
    isbn: book.isbn,
    owned: book.owned,
    readingStatus: book.reading_status,
    startedOn: book.started_on,
    finishedOn: book.finished_on,
    rating: book.rating,
    lentTo: book.lent_to,
    lentOn: book.lent_on,
});
