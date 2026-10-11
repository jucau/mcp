<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Marque-page</title>
    <style>
        :root {
            color-scheme: light dark;
            --ground: #edeff2;
            --card: #fdfdfb;
            --ink: #1d2738;
            --ink-soft: #55607a;
            --rule: #d3d8e1;
            --ribbon: #2547c9;
            --on-ribbon: #fdfdfb;
        }

        @media (prefers-color-scheme: dark) {
            :root {
                --ground: #121620;
                --card: #1c2230;
                --ink: #e6e9ef;
                --ink-soft: #9aa3b5;
                --rule: #2b3344;
                --ribbon: #6f8cff;
                --on-ribbon: #121620;
            }
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 2rem 1rem;
            background: var(--ground);
            color: var(--ink);
            font: 17px/1.6 system-ui, sans-serif;
        }

        main {
            position: relative;
            width: 100%;
            max-width: 40rem;
            padding: 3.5rem 2.5rem 2.5rem;
            background: var(--card);
            border: 1px solid var(--rule);
            border-radius: 4px;
            box-shadow: 0 24px 48px -32px rgb(20 30 50 / 0.5);
        }

        .ribbon {
            position: absolute;
            top: -1px;
            right: 2.5rem;
            width: 2rem;
            height: 4.5rem;
            background: var(--ribbon);
            clip-path: polygon(0 0, 100% 0, 100% 100%, 50% 78%, 0 100%);
        }

        h1 {
            margin: 0;
            font: 400 3rem/1.05 'Libre Caslon Display', Baskerville, Georgia, serif;
            letter-spacing: -0.01em;
        }

        .tagline {
            margin: 0.5rem 0 1.75rem;
            color: var(--ink-soft);
            font-size: 1.1rem;
        }

        p { margin: 0 0 1rem; }

        .open {
            display: inline-block;
            margin: 0.75rem 0 2rem;
            padding: 0.7rem 1.4rem;
            background: var(--ribbon);
            color: var(--on-ribbon);
            border-radius: 6px;
            font-weight: 600;
            text-decoration: none;
        }

        .open:hover, .open:focus-visible { filter: brightness(1.1); }

        dl {
            margin: 0;
            padding-top: 1.5rem;
            border-top: 1px solid var(--rule);
            display: grid;
            grid-template-columns: max-content 1fr;
            gap: 0.6rem 1.25rem;
            font-size: 0.95rem;
        }

        dt { font: 500 0.85rem/1.9 ui-monospace, 'SFMono-Regular', Menlo, monospace; }
        dd { margin: 0; color: var(--ink-soft); }

        @media (max-width: 30rem) {
            main { padding: 3.5rem 1.5rem 2rem; }
            h1 { font-size: 2.4rem; }
            dl { grid-template-columns: 1fr; gap: 0 0; }
            dd { margin-bottom: 0.75rem; }
        }
    </style>
</head>
<body>
    <main>
        <div class="ribbon" aria-hidden="true"></div>

        <h1>Marque-page</h1>
        <p class="tagline">Une bibliothèque personnelle, à piloter en langage naturel.</p>

        <p>
            Les livres qu'on possède, qu'on lit, qu'on a lus, qu'on a prêtés ou qu'on aimerait lire. Marque-page les
            range, et un agent IA peut les consulter et les mettre à jour à votre place : « qu'est-ce que je suis en
            train de lire ? », « ajoute Le Sorceleur à ma liste d'envies ».
        </p>
        <p>
            C'est un projet pédagogique : il sert à comprendre le <em>Model Context Protocol</em> en construisant un
            serveur MCP sur Laravel, autour d'un domaine volontairement petit.
        </p>

        <a class="open" href="{{ config('library.front_url') }}">Ouvrir ma bibliothèque</a>

        <dl>
            <dt>/mcp</dt>
            <dd>Le serveur MCP, pour un agent comme Claude Code : des tools, des resources et des prompts.</dd>
            <dt>/api</dt>
            <dd>L'API REST, que l'application web utilise. Les mêmes cas d'usage, un autre adaptateur.</dd>
            <dt>/login</dt>
            <dd>La connexion au serveur d'autorisation, qui délivre les tokens des deux précédents.</dd>
        </dl>
    </main>
</body>
</html>
