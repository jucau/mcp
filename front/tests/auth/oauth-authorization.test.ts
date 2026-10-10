// @vitest-environment node
import { OAuthAuthorization } from '../../src/auth/adapters/oauth/oauth-authorization';
import { API, CALLBACK, CLIENT_ID, FakeAuthorizationServer } from '../support/auth/fake-authorization-server';
import { MemoryStorage } from '../support/auth/memory-storage';

describe('signing in with OAuth', () => {
    const authorizationServer = new FakeAuthorizationServer();
    const pending = new MemoryStorage();
    let clock = 0;
    let redirectedTo: URL | undefined;

    const authorization = () =>
        new OAuthAuthorization(
            {
                apiUrl: new URL(API),
                probePath: 'books',
                clientId: CLIENT_ID,
                redirectUri: new URL(CALLBACK),
                allowInsecureRequests: true,
            },
            pending,
            (url) => {
                redirectedTo = url;
            },
            () => clock,
        );

    const signInThroughTheAuthorizationServer = async (client: OAuthAuthorization): Promise<void> => {
        await client.signIn();
        await client.completeSignIn(authorizationServer.approve(redirected()));
    };

    const redirected = (): URL => {
        if (redirectedTo === undefined) {
            throw new Error('The client did not go to the authorization server.');
        }

        return redirectedTo;
    };

    beforeAll(() => {
        authorizationServer.server.listen({ onUnhandledRequest: 'error' });
    });
    afterAll(() => {
        authorizationServer.server.close();
    });
    beforeEach(() => {
        clock = 0;
        redirectedTo = undefined;
        authorizationServer.reset();
        pending.clear();
    });

    it('follows the 401 of the API to its authorization server, and asks for a code for the API with PKCE', async () => {
        await authorization().signIn();

        const url = redirected();
        expect(url.origin + url.pathname).toBe('http://api.test/oauth/authorize');
        expect(Object.fromEntries(url.searchParams)).toMatchObject({
            response_type: 'code',
            client_id: CLIENT_ID,
            redirect_uri: CALLBACK,
            code_challenge_method: 'S256',
            resource: API,
        });
        expect(url.searchParams.get('code_challenge')).toMatch(/^[\w-]{43}$/);
        expect(url.searchParams.get('state')).toMatch(/^[\w-]+$/);
    });

    it('refuses metadata that name another resource than the API it meant to reach', async () => {
        authorizationServer.announcedResource = 'http://elsewhere.test/api';

        await expect(authorization().signIn()).rejects.toThrow();
        expect(redirectedTo).toBeUndefined();
    });

    it('exchanges the code with its verifier for a token of the API, kept in memory only', async () => {
        const client = authorization();

        await signInThroughTheAuthorizationServer(client);

        expect(client.isSignedIn()).toBe(true);
        await expect(client.accessToken()).resolves.toBe('access-1');
        expect(authorizationServer.tokenRequests.at(-1)?.get('resource')).toBe(API);
        expect(pending.length).toBe(0);
    });

    it('refuses a callback that does not carry the state it sent', async () => {
        const client = authorization();
        await client.signIn();
        const callback = authorizationServer.approve(redirected());
        callback.searchParams.set('state', 'forged');

        await expect(client.completeSignIn(callback)).rejects.toThrow();
        expect(client.isSignedIn()).toBe(false);
    });

    it('refuses a callback when no sign-in was started from this tab', async () => {
        await expect(authorization().completeSignIn(new URL(`${CALLBACK}?code=code-1&state=any`))).rejects.toThrow(
            'No sign-in was started from this tab',
        );
    });

    it('refreshes the token for the same API before it expires', async () => {
        const client = authorization();
        await signInThroughTheAuthorizationServer(client);

        clock = 3600 * 1000 - 29_000;

        await expect(client.accessToken()).resolves.toBe('access-2');
        expect(authorizationServer.tokenRequests.at(-1)?.get('grant_type')).toBe('refresh_token');
        expect(authorizationServer.tokenRequests.at(-1)?.get('resource')).toBe(API);
    });

    it('keeps the token while it is still fresh', async () => {
        const client = authorization();
        await signInThroughTheAuthorizationServer(client);

        clock = 3600 * 1000 - 31_000;

        await expect(client.accessToken()).resolves.toBe('access-1');
    });

    it('has no token before signing in', async () => {
        const client = authorization();

        expect(client.isSignedIn()).toBe(false);
        await expect(client.accessToken()).resolves.toBeUndefined();
    });
});
