import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import { BrowserRouter } from 'react-router';
import { OAuthAuthorization } from '../auth/adapters/oauth/oauth-authorization';
import { HttpLibrary } from '../library/adapters/http/http-library';
import { App } from './App';
import './theme.css';

const root = document.getElementById('root');

if (root === null) {
    throw new Error('The page has no #root element to render the application into.');
}

const apiUrl = new URL(import.meta.env.VITE_API_URL);
const authorization = new OAuthAuthorization(
    {
        apiUrl,
        probePath: 'books',
        clientId: import.meta.env.VITE_OAUTH_CLIENT_ID,
        redirectUri: new URL('/callback', window.location.origin),
        allowInsecureRequests: import.meta.env.DEV,
    },
    sessionStorage,
    (url) => {
        window.location.assign(url);
    },
);

createRoot(root).render(
    <StrictMode>
        <BrowserRouter>
            <App authorization={authorization} library={new HttpLibrary(apiUrl, authorization)} />
        </BrowserRouter>
    </StrictMode>,
);
