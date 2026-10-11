// @vitest-environment node
import { http, HttpResponse } from 'msw';
import { setupServer } from 'msw/node';
import { NotSignedIn } from '../../../src/auth/core/model/not-signed-in';
import { HttpLibrary } from '../../../src/library/adapters/http/http-library';
import type { Library } from '../../../src/library/core/ports/library';
import { SignedIn } from '../../support/auth/signed-in';

const API = 'http://api.test/api';
const ID = '0199a3c2-0000-7000-8000-000000000001';
const calls: { method: string; path: string; body: unknown }[] = [];
let answer: () => Response;

const book = {
    id: ID,
    title: 'Dune',
    author: 'Frank Herbert',
    isbn: null,
    owned: true,
    reading_status: 'reading',
    started_on: '2026-10-11',
    finished_on: null,
    rating: null,
    lent_to: null,
    lent_on: null,
};

const problem = (status: number, type: string, detail: string) =>
    HttpResponse.json(
        { type: `http://api.test/problems/${type}`, title: type, status, detail },
        { status, headers: { 'Content-Type': 'application/problem+json' } },
    );

const server = setupServer(
    http.all(`${API}/books/:book/*`, async ({ request }) => {
        calls.push({
            method: request.method,
            path: new URL(request.url).pathname,
            body: await request.text().then((text) => (text === '' ? null : (JSON.parse(text) as unknown))),
        });

        return answer();
    }),
    http.delete(`${API}/books/:book`, ({ request }) => {
        calls.push({ method: request.method, path: new URL(request.url).pathname, body: null });

        return answer();
    }),
);

describe('acting on a book over HTTP', () => {
    const library = () => new HttpLibrary(new URL(API), new SignedIn());

    beforeAll(() => {
        server.listen({ onUnhandledRequest: 'error' });
    });
    afterAll(() => {
        server.close();
    });
    beforeEach(() => {
        calls.length = 0;
        answer = () => HttpResponse.json({ data: book });
    });

    it.each<[string, (library: Library) => Promise<unknown>, string, unknown]>([
        ['start reading', (l) => l.startReading(ID), 'start-reading', null],
        ['finish reading', (l) => l.finishReading(ID), 'finish-reading', null],
        ['abandon reading', (l) => l.abandonReading(ID), 'abandon-reading', null],
        ['rate', (l) => l.rate(ID, 4), 'rate', { rating: 4 }],
        ['lend', (l) => l.lend(ID, 'Marie'), 'lend', { borrower: 'Marie' }],
        ['return', (l) => l.returnBook(ID), 'return', null],
        ['acquire', (l) => l.acquire(ID), 'acquire', null],
    ])('%s posts to its own URL and gives back the updated book', async (_, act, path, body) => {
        const outcome = await act(library());

        expect(calls).toEqual([{ method: 'POST', path: `/api/books/${ID}/${path}`, body }]);
        expect(outcome).toMatchObject({
            ok: true,
            value: { id: ID, readingStatus: 'reading', startedOn: '2026-10-11' },
        });
    });

    it('turns a refusal of the domain into a refusal, named by the last segment of its type', async () => {
        answer = () =>
            problem(409, 'reading-transition-not-allowed', 'Cannot start reading "Dune": its reading status is read.');

        expect(await library().startReading(ID)).toEqual({
            ok: false,
            refusal: {
                type: 'reading-transition-not-allowed',
                detail: 'Cannot start reading "Dune": its reading status is read.',
            },
        });
    });

    it.each([404, 422])('turns a %i problem into a refusal too', async (status) => {
        answer = () => problem(status, 'some-refusal', 'Refused.');

        expect(await library().rate(ID, 9)).toEqual({
            ok: false,
            refusal: { type: 'some-refusal', detail: 'Refused.' },
        });
    });

    it('removes a book, the API answering with no content', async () => {
        answer = () => new HttpResponse(null, { status: 204 });

        expect(await library().remove(ID)).toEqual({ ok: true, value: null });
        expect(calls).toEqual([{ method: 'DELETE', path: `/api/books/${ID}`, body: null }]);
    });

    it('gives back the refusal of a removal', async () => {
        answer = () => problem(409, 'book-lent', '"Dune" is lent to Paul.');

        expect(await library().remove(ID)).toEqual({
            ok: false,
            refusal: { type: 'book-lent', detail: '"Dune" is lent to Paul.' },
        });
    });

    it('says the session is over on a 401', async () => {
        answer = () => HttpResponse.json({ message: 'Unauthenticated.' }, { status: 401 });

        await expect(library().acquire(ID)).rejects.toBeInstanceOf(NotSignedIn);
    });

    it('treats any other failure as a breakdown, not as a refusal', async () => {
        answer = () => HttpResponse.json({ message: 'Server Error' }, { status: 500 });

        await expect(library().acquire(ID)).rejects.toThrow('The API answered 500.');
    });

    it('treats a refusal status without a problem as a breakdown', async () => {
        answer = () => HttpResponse.json({ message: 'odd' }, { status: 409 });

        await expect(library().acquire(ID)).rejects.toThrow('The API answered 409.');
    });
});
