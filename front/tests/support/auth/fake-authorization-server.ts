import { http, HttpResponse } from 'msw';
import { setupServer } from 'msw/node';

export const ORIGIN = 'http://api.test';
export const API = `${ORIGIN}/api`;
export const CALLBACK = 'http://front.test/callback';
export const CLIENT_ID = 'front';

interface Grant {
    readonly challenge: string;
    readonly resource: string;
}

const s256 = async (verifier: string): Promise<string> => {
    const digest = await crypto.subtle.digest('SHA-256', new TextEncoder().encode(verifier));

    return btoa(String.fromCharCode(...new Uint8Array(digest)))
        .replaceAll('+', '-')
        .replaceAll('/', '_')
        .replaceAll('=', '');
};

export class FakeAuthorizationServer {
    announcedResource = API;
    tokenRequests: URLSearchParams[] = [];
    private grants = new Map<string, Grant>();
    private issued = 0;

    readonly server = setupServer(
        http.get(`${API}/books`, () =>
            HttpResponse.json(
                { message: 'Unauthenticated.' },
                {
                    status: 401,
                    headers: {
                        'WWW-Authenticate': `Bearer resource_metadata="${ORIGIN}/.well-known/oauth-protected-resource/api"`,
                    },
                },
            ),
        ),
        http.get(`${ORIGIN}/.well-known/oauth-protected-resource/api`, () =>
            HttpResponse.json({ resource: this.announcedResource, authorization_servers: [ORIGIN] }),
        ),
        http.get(`${ORIGIN}/.well-known/oauth-authorization-server`, () =>
            HttpResponse.json({
                issuer: ORIGIN,
                authorization_endpoint: `${ORIGIN}/oauth/authorize`,
                token_endpoint: `${ORIGIN}/oauth/token`,
                code_challenge_methods_supported: ['S256'],
            }),
        ),
        http.post(`${ORIGIN}/oauth/token`, async ({ request }) => {
            const body = new URLSearchParams(await request.text());
            this.tokenRequests.push(body);

            return (await this.accepts(body))
                ? HttpResponse.json({
                      access_token: `access-${String(++this.issued)}`,
                      refresh_token: `refresh-${String(this.issued)}`,
                      token_type: 'Bearer',
                      expires_in: 3600,
                  })
                : HttpResponse.json({ error: 'invalid_grant' }, { status: 400 });
        }),
    );

    reset(): void {
        this.announcedResource = API;
        this.tokenRequests = [];
        this.grants = new Map();
        this.issued = 0;
    }

    approve(authorization: URL): URL {
        const code = `code-${String(this.grants.size + 1)}`;
        this.grants.set(code, {
            challenge: authorization.searchParams.get('code_challenge') ?? '',
            resource: authorization.searchParams.get('resource') ?? '',
        });

        const callback = new URL(CALLBACK);
        callback.search = new URLSearchParams({
            code,
            state: authorization.searchParams.get('state') ?? '',
        }).toString();

        return callback;
    }

    private async accepts(body: URLSearchParams): Promise<boolean> {
        if (body.get('client_id') !== CLIENT_ID) {
            return false;
        }

        if (body.get('grant_type') === 'refresh_token') {
            return body.get('refresh_token') === `refresh-${String(this.issued)}`;
        }

        const grant = this.grants.get(body.get('code') ?? '');
        const challenge = await s256(body.get('code_verifier') ?? '');

        return grant?.challenge === challenge && grant.resource === body.get('resource');
    }
}
