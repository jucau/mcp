import react from '@vitejs/plugin-react';
import { defineConfig } from 'vitest/config';

export default defineConfig({
    plugins: [react()],
    server: { port: 5173, strictPort: true, watch: { ignored: ['**/.stryker-tmp/**', '**/reports/**'] } },
    test: {
        environment: 'jsdom',
        globals: true,
        include: ['tests/**/*.test.{ts,tsx}'],
        setupFiles: ['tests/setup.ts'],
        restoreMocks: true,
    },
});
