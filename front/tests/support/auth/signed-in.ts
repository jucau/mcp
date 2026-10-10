import type { Authorization } from '../../../src/auth/core/ports/authorization';

export class SignedIn implements Authorization {
    isSignedIn(): boolean {
        return true;
    }

    signIn(): Promise<void> {
        return Promise.resolve();
    }

    completeSignIn(): Promise<void> {
        return Promise.resolve();
    }

    accessToken(): Promise<string> {
        return Promise.resolve('token');
    }
}
