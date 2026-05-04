<?php
/** @var string $title */
/** @var string $content */
use App\Helpers\View;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= View::escape($title ?? 'Giftcards') ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>[x-cloak] { display: none !important; }</style>
</head>
<body class="bg-slate-100 min-h-screen text-slate-900">
    <header class="bg-white border-b border-slate-200">
        <div class="max-w-2xl mx-auto px-4 py-3 flex items-center justify-between">
            <span class="text-lg font-semibold text-slate-800">Giftcards</span>
        </div>
    </header>

    <main class="max-w-2xl mx-auto px-4 py-6 sm:py-8">
        <?= $content ?? '' ?>
    </main>

    <footer class="max-w-2xl mx-auto px-4 py-6 text-center text-xs text-slate-400">
        Sistema de gestión de giftcards
    </footer>

    <script>
        // Helper de fetch común. Si no hay JWT, no manda Authorization (request público).
        // Si hay (improbable en layout público pero por consistencia), lo manda.
        window.api = async function (path, options) {
            options = options || {};
            const headers = Object.assign({}, options.headers || {});
            const token = localStorage.getItem('auth_token');
            if (token) headers['Authorization'] = 'Bearer ' + token;
            if (options.body && !(options.body instanceof FormData) && !headers['Content-Type']) {
                headers['Content-Type'] = 'application/json';
            }
            const res = await fetch(path, Object.assign({}, options, { headers, credentials: 'same-origin' }));
            return res;
        };
    </script>
</body>
</html>
