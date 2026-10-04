<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Marque-page — @yield('title')</title>
    <style>
        body { font-family: system-ui, sans-serif; max-width: 24rem; margin: 4rem auto; padding: 0 1rem; color: #1c1917; }
        label, input, button { display: block; width: 100%; box-sizing: border-box; }
        input { margin: .25rem 0 1rem; padding: .5rem; }
        button { padding: .6rem; cursor: pointer; border-radius: .375rem; border: 1px solid #a8a29e; background: #fff; }
        button.primary { background: #1c1917; color: #fff; border-color: #1c1917; }
        .error { color: #b00020; }
        .box { border: 1px solid #d6d3d1; border-radius: .5rem; padding: .75rem 1rem; margin: 1rem 0; }
        .muted { color: #57534e; font-size: .9rem; }
        .actions { display: flex; gap: .75rem; margin-top: 1.5rem; }
        .actions form { flex: 1; }
    </style>
</head>
<body>
    <h1>Marque-page</h1>
    @yield('content')
</body>
</html>
