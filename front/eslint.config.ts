import js from '@eslint/js';
import { defineConfig } from 'eslint/config';
import type { Linter } from 'eslint';
import prettier from 'eslint-config-prettier';
import reactHooks from 'eslint-plugin-react-hooks';
import tseslint from 'typescript-eslint';

const frameworks = [
    'react',
    'react-dom',
    'react-dom/*',
    'react-router',
    '@tanstack/*',
    'openapi-fetch',
    'oauth4webapi',
];

const layer = (files: string, forbidden: string[], packages: string[] = []): Linter.Config => ({
    files: [files],
    rules: {
        '@typescript-eslint/no-restricted-imports': [
            'error',
            {
                patterns: [
                    ...forbidden.map((other) => ({
                        group: [`**/${other}`, `**/${other}/**`],
                        message: `This layer must not depend on ${other} (ADR-0029).`,
                    })),
                    ...(packages.length > 0
                        ? [
                              {
                                  group: packages,
                                  message: 'The core knows no framework, no HTTP client and no browser (ADR-0029).',
                              },
                          ]
                        : []),
                ],
            },
        ],
    },
});

export default defineConfig(
    { ignores: ['dist', 'reports', '.stryker-tmp', 'src/library/adapters/http/contract.ts'] },
    js.configs.recommended,
    tseslint.configs.strictTypeChecked,
    tseslint.configs.stylisticTypeChecked,
    reactHooks.configs.flat.recommended,
    { languageOptions: { parserOptions: { projectService: true, tsconfigRootDir: import.meta.dirname } } },
    layer('src/*/core/**', ['adapters', 'ui', 'app'], frameworks),
    layer('src/*/adapters/**', ['ui', 'app']),
    layer('src/*/ui/**', ['adapters', 'app']),
    prettier,
);
