import * as oauth from 'oauth4webapi';
import type { Authorization } from '../../core/ports/authorization';

export interface OAuthSettings {
    readonly apiUrl: URL;
    readonly probePath: string;
    readonly clientId: string;
    readonly redirectUri: URL;
    readonly allowInsecureRequests: boolean;
}

interface Discovered {
    readonly server: oauth.AuthorizationServer;
    readonly resource: string;
}

interface Tokens {
    readonly access: string;
    readonly refresh: string | undefined;
    readonly expiresAt: number;
}

const PENDING = 'marque-page.oauth.pending';
// eslint-disable-next-line @typescript-eslint/no-deprecated -- the local stack has no TLS: only the dev build turns it on
const INSECURE: typeof oauth.allowInsecureRequests = oauth.allowInsecureRequests;
const EXPIRY_MARGIN_MS = 30_000;

export class OAuthAuthorization implements Authorization {
    private readonly client: oauth.Client;
    private discovered: Promise<Discovered> | undefined;
    private tokens: Tokens | undefined;

    constructor(
        private readonly settings: OAuthSettings,
        private readonly pending: Storage,
        private readonly navigate: (url: URL) => void,
        private readonly now: () => number = Date.now,
    ) {
        this.client = { client_id: settings.clientId };
    }

    isSignedIn(): boolean {
        return this.tokens !== undefined;
    }

    async signIn(): Promise<void> {
        const { server, resource } = await this.discover();
        const verifier = oauth.generateRandomCodeVerifier();
        const state = oauth.generateRandomState();
        this.pending.setItem(PENDING, JSON.stringify({ verifier, state }));

        const url = new URL(this.endpoint(server.authorization_endpoint));
        url.search = new URLSearchParams({
            response_type: 'code',
            client_id: this.settings.clientId,
            redirect_uri: this.settings.redirectUri.href,
            code_challenge: await oauth.calculatePKCECodeChallenge(verifier),
            code_challenge_method: 'S256',
            state,
            resource,
        }).toString();

        this.navigate(url);
    }

    async completeSignIn(callback: URL): Promise<void> {
        const { verifier, state } = this.takePending();
        const { server, resource } = await this.discover();
        const parameters = oauth.validateAuthResponse(server, this.client, callback, state);

        const response = await oauth.authorizationCodeGrantRequest(
            server,
            this.client,
            oauth.None(),
            parameters,
            this.settings.redirectUri.href,
            verifier,
            this.options({ resource }),
        );

        this.keep(await oauth.processAuthorizationCodeResponse(server, this.client, response));
    }

    async accessToken(): Promise<string | undefined> {
        if (this.tokens === undefined) {
            return undefined;
        }

        if (this.now() < this.tokens.expiresAt - EXPIRY_MARGIN_MS) {
            return this.tokens.access;
        }

        return this.refresh(this.tokens.refresh);
    }

    private async refresh(refreshToken: string | undefined): Promise<string | undefined> {
        this.tokens = undefined;

        if (refreshToken === undefined) {
            return undefined;
        }

        const { server, resource } = await this.discover();
        const response = await oauth.refreshTokenGrantRequest(
            server,
            this.client,
            oauth.None(),
            refreshToken,
            this.options({ resource }),
        );
        return this.keep(await oauth.processRefreshTokenResponse(server, this.client, response));
    }

    private keep(response: oauth.TokenEndpointResponse): string {
        this.tokens = {
            access: response.access_token,
            refresh: response.refresh_token,
            expiresAt: this.now() + (response.expires_in ?? 0) * 1000,
        };

        return response.access_token;
    }

    // RFC 9728: the client starts from the 401 of the API, follows the header to the metadata of the resource, and
    // checks they name the API it meant to reach before trusting the authorization server they point to.
    private discover(): Promise<Discovered> {
        this.discovered ??= (async () => {
            const challenge = await fetch(new URL(this.settings.probePath, `${this.settings.apiUrl.href}/`));
            const metadataUrl = /resource_metadata="([^"]+)"/.exec(
                challenge.headers.get('WWW-Authenticate') ?? '',
            )?.[1];

            if (challenge.status !== 401 || metadataUrl === undefined) {
                throw new Error(`The API did not say where to get a token (status ${String(challenge.status)}).`);
            }

            const resource = await oauth.processResourceDiscoveryResponse(
                this.settings.apiUrl,
                await fetch(metadataUrl),
            );
            const issuer = resource.authorization_servers?.[0];

            if (issuer === undefined) {
                throw new Error('The API names no authorization server.');
            }

            const issuerUrl = new URL(issuer);
            const server = await oauth.processDiscoveryResponse(
                issuerUrl,
                await oauth.discoveryRequest(issuerUrl, { algorithm: 'oauth2', ...this.insecure() }),
            );

            return { server, resource: resource.resource };
        })();

        return this.discovered;
    }

    private takePending(): { verifier: string; state: string } {
        const pending = this.pending.getItem(PENDING);
        this.pending.removeItem(PENDING);
        const parsed: unknown = pending === null ? null : JSON.parse(pending);

        if (
            typeof parsed !== 'object' ||
            parsed === null ||
            !('verifier' in parsed) ||
            !('state' in parsed) ||
            typeof parsed.verifier !== 'string' ||
            typeof parsed.state !== 'string'
        ) {
            throw new Error('No sign-in was started from this tab: start again.');
        }

        return { verifier: parsed.verifier, state: parsed.state };
    }

    private endpoint(url: string | undefined): string {
        if (url === undefined) {
            throw new Error('The authorization server announces no such endpoint.');
        }

        return url;
    }

    private options(additionalParameters: Record<string, string>): oauth.TokenEndpointRequestOptions {
        return { additionalParameters, ...this.insecure() };
    }

    private insecure() {
        return { [INSECURE]: this.settings.allowInsecureRequests };
    }
}
