// @vitest-environment node
import { http, HttpResponse } from 'msw';
import { setupServer } from 'msw/node';
import { NotSignedIn } from '../../../src/auth/core/model/not-signed-in';
import { HttpLibrary } from '../../../src/library/adapters/http/http-library';
import { SignedIn } from '../../support/auth/signed-in';

const API = 'http://api.test/api';
const received: URL[] = [];
let status = 200;

const server = setupServer(
    http.get(`${API}/books`, ({ request }) => {
        received.push(new URL(request.url));

        if (request.headers.get('Authorization') !== 'Bearer token' || status === 401) {
            return HttpResponse.json({ message: 'Unauthenticated.' }, { status: 401 });
        }

        return HttpResponse.json({
            data: [
                {
                    id: '0199a3c2-0000-7000-8000-000000000001',
                    title: 'Dune',
                    author: 'Frank Herbert',
                    isbn: '9780441172719',
                    owned: true,
                    reading_status: 'read',
                    started_on: '2025-01-04',
                    finished_on: '2025-02-10',
                    rating: 5,
                    lent_to: 'Paul',
                    lent_on: '2026-07-03',
                },
            ],
            links: { first: null, last: null, prev: null, next: null },
            meta: {
                current_page: 2,
                per_page: 20,
                total: 21,
                from: 21,
                to: 21,
                last_page: 2,
                path: `${API}/books`,
                links: [],
            },
        });
    }),
);

describe('the library over HTTP', () => {
    const library = () => new HttpLibrary(new URL(API), new SignedIn());

    beforeAll(() => {
        server.listen({ onUnhandledRequest: 'error' });
    });
    afterAll(() => {
        server.close();
    });
    beforeEach(() => {
        received.length = 0;
        status = 200;
    });

    it('asks the API with its query string, booleans spelled as the contract wants', async () => {
        await library().search({ text: 'dune', readingStatus: 'read', owned: true, lent: false, page: 2, perPage: 20 });

        expect(Object.fromEntries(received[0]?.searchParams ?? [])).toEqual({
            query: 'dune',
            reading_status: 'read',
            owned: 'true',
            lent: 'false',
            page: '2',
            per_page: '20',
        });
    });

    it('sends no empty search', async () => {
        await library().search({ text: '' });

        expect(received[0]?.search).toBe('');
    });

    it('reads the page of books the API answers', async () => {
        expect(await library().search({})).toEqual({
            items: [
                {
                    id: '0199a3c2-0000-7000-8000-000000000001',
                    title: 'Dune',
                    author: 'Frank Herbert',
                    isbn: '9780441172719',
                    owned: true,
                    readingStatus: 'read',
                    startedOn: '2025-01-04',
                    finishedOn: '2025-02-10',
                    rating: 5,
                    lentTo: 'Paul',
                    lentOn: '2026-07-03',
                },
            ],
            page: 2,
            perPage: 20,
            total: 21,
        });
    });

    it('says the session is over when the API refuses the token', async () => {
        status = 401;

        await expect(library().search({})).rejects.toBeInstanceOf(NotSignedIn);
    });

    it('does not even call the API without a token', async () => {
        const signedOut = new SignedIn();
        signedOut.accessToken = () => Promise.resolve(undefined);

        await expect(new HttpLibrary(new URL(API), signedOut).search({})).rejects.toBeInstanceOf(NotSignedIn);
        expect(received).toHaveLength(0);
    });
});
