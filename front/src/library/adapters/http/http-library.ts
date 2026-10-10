import createClient, { type Client, type Middleware } from 'openapi-fetch';
import { NotSignedIn } from '../../../auth/core/model/not-signed-in';
import type { Authorization } from '../../../auth/core/ports/authorization';
import type { Book } from '../../core/model/book';
import type { BookSearch } from '../../core/model/book-search';
import type { Page } from '../../core/model/page';
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
                    ...(criteria.text === undefined ? {} : { query: criteria.text }),
                    ...(criteria.readingStatus === undefined ? {} : { reading_status: criteria.readingStatus }),
                    ...(criteria.owned === undefined ? {} : { owned: flag(criteria.owned) }),
                    ...(criteria.lent === undefined ? {} : { lent: flag(criteria.lent) }),
                    ...(criteria.page === undefined ? {} : { page: criteria.page }),
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
