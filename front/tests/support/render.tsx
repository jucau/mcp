import { render } from '@testing-library/react';
import { MemoryRouter } from 'react-router';
import { App } from '../../src/app/App';
import type { Authorization } from '../../src/auth/core/ports/authorization';
import type { Library } from '../../src/library/core/ports/library';
import { SignedIn } from './auth/signed-in';

export const renderApp = (library: Library, authorization: Authorization = new SignedIn()) =>
    render(
        <MemoryRouter>
            <App authorization={authorization} library={library} />
        </MemoryRouter>,
    );
