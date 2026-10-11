// @vitest-environment node
import { http, HttpResponse } from 'msw';
import { setupServer } from 'msw/node';
import { HttpLibrary } from '../../../src/library/adapters/http/http-library';
import { SignedIn } from '../../support/auth/signed-in';

const API = 'http://api.test/api';
const asked: string[] = [];
let answer: () => Response;

const problem = (status: number, type: string, detail: string) =>
    HttpResponse.json(
        { type: `http://api.test/problems/${type}`, title: type, status, detail },
        { status, headers: { 'Content-Type': 'application/problem+json' } },
    );

const server = setupServer(
    http.get(`${API}/catalog/:isbn`, ({ request }) => {
        asked.push(new URL(request.url).pathname);

        return answer();
    }),
);

describe('looking a book up over HTTP', () => {
    const library = () => new HttpLibrary(new URL(API), new SignedIn());

    beforeAll(() => {
        server.listen({ onUnhandledRequest: 'error' });
    });
    afterAll(() => {
        server.close();
    });
    beforeEach(() => {
        asked.length = 0;
        answer = () =>
            HttpResponse.json({
                data: { isbn: '9780441172719', title: 'Dune', author: 'Frank Herbert', published_in: 1965, pages: 896 },
            });
    });

    it('asks the catalog of the API for the ISBN, and reads its entry', async () => {
        expect(await library().lookUp('0-441-17271-7')).toEqual({
            ok: true,
            value: { isbn: '9780441172719', title: 'Dune', author: 'Frank Herbert', publishedIn: 1965, pages: 896 },
        });
        expect(asked).toEqual(['/api/catalog/0-441-17271-7']);
    });

    it('reads an entry that knows nothing but its title', async () => {
        answer = () =>
            HttpResponse.json({
                data: { isbn: '9780441172719', title: 'Dune', author: null, published_in: null, pages: null },
            });

        expect(await library().lookUp('9780441172719')).toEqual({
            ok: true,
            value: { isbn: '9780441172719', title: 'Dune', author: null, publishedIn: null, pages: null },
        });
    });

    it.each([
        [404, 'not-in-catalog'],
        [422, 'invalid-isbn'],
        [503, 'catalog-unavailable'],
    ])('gives back the refusal of a %i, named by the last segment of its type', async (status, type) => {
        answer = () => problem(status, type, 'What the API said.');

        expect(await library().lookUp('9780441172719')).toEqual({
            ok: false,
            refusal: { type, detail: 'What the API said.' },
        });
    });

    it('does not take a failure of the server for a refusal', async () => {
        answer = () => new HttpResponse('Service Unavailable', { status: 503 });

        await expect(library().lookUp('9780441172719')).rejects.toThrow('The API answered 503.');
    });
});
