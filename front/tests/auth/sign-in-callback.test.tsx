import { render, screen } from '@testing-library/react';
import { StrictMode } from 'react';
import { MemoryRouter, Route, Routes } from 'react-router';
import { AuthorizationProvider } from '../../src/auth/ui/authorization-context';
import { SignInCallback } from '../../src/auth/ui/SignInCallback';
import { SignedIn } from '../support/auth/signed-in';

class CountingCallbacks extends SignedIn {
    completed = 0;

    override completeSignIn(): Promise<void> {
        this.completed++;

        return this.completed > 1 ? Promise.reject(new Error('code already used')) : Promise.resolve();
    }
}

const renderCallback = (authorization: SignedIn) =>
    render(
        <StrictMode>
            <AuthorizationProvider authorization={authorization}>
                <MemoryRouter initialEntries={['/callback']}>
                    <Routes>
                        <Route path="/callback" element={<SignInCallback />} />
                        <Route path="/" element={<p>La bibliothèque</p>} />
                    </Routes>
                </MemoryRouter>
            </AuthorizationProvider>
        </StrictMode>,
    );

describe('coming back from the authorization server', () => {
    it('exchanges the code once, even when React runs the effect twice, then goes to the library', async () => {
        const authorization = new CountingCallbacks();

        renderCallback(authorization);

        expect(await screen.findByText('La bibliothèque')).toBeInTheDocument();
        expect(authorization.completed).toBe(1);
    });

    it('says why the sign-in failed and offers to start again', async () => {
        const authorization = new SignedIn();
        authorization.completeSignIn = () =>
            Promise.reject(new Error('No sign-in was started from this tab: start again.'));

        renderCallback(authorization);

        expect(await screen.findByRole('alert')).toHaveTextContent('No sign-in was started from this tab');
        expect(screen.getByRole('link', { name: 'Recommencer' })).toHaveAttribute('href', '/');
    });
});
