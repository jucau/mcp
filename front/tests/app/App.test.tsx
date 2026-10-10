import { render, screen } from '@testing-library/react';
import { App } from '../../src/app/App';

describe('the application', () => {
    it('says whose library it is', () => {
        render(<App />);

        expect(screen.getByRole('heading', { level: 1, name: 'Marque-page' })).toBeInTheDocument();
    });
});
