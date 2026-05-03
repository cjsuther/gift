<?php
/** @var \App\Auth\AuthenticatedUser $user */
/** @var string $title */
/** @var string $content */
use App\Helpers\View;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= View::escape($title ?? 'Panel') ?> — Giftcards</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>[x-cloak] { display: none !important; }</style>
</head>
<body class="bg-slate-100 min-h-screen text-slate-900">
    <?php
    $homeUrl = $user->isSuperAdmin() ? '/admin/establishments' : '/dashboard';
    $navItems = $user->isSuperAdmin()
        ? [
            ['url' => '/admin/establishments', 'label' => 'Establecimientos'],
            ['url' => '/admin/users',          'label' => 'Usuarios'],
        ]
        : (
            $user->isEstablishmentAdmin()
                ? [
                    ['url' => '/dashboard', 'label' => 'Dashboard'],
                    ['url' => '/giftcards', 'label' => 'Giftcards'],
                    ['url' => '/scan',      'label' => 'Escanear'],
                    ['url' => '/users',     'label' => 'Usuarios'],
                ]
                : [
                    ['url' => '/scan',      'label' => 'Escanear'],
                    ['url' => '/giftcards', 'label' => 'Giftcards'],
                ]
        );
    ?>
    <header class="bg-white border-b border-slate-200">
        <div class="max-w-6xl mx-auto px-4 py-3 flex items-center justify-between">
            <div class="flex items-center gap-6">
                <a href="<?= View::escape($homeUrl) ?>" class="text-lg font-semibold text-slate-800">Giftcards</a>
                <nav class="flex items-center gap-4 text-sm">
                    <?php foreach ($navItems as $item): ?>
                        <?php if (!empty($item['disabled'])): ?>
                            <span class="text-slate-300 cursor-not-allowed" title="Disponible en próxima fase">
                                <?= View::escape($item['label']) ?>
                            </span>
                        <?php else: ?>
                            <a href="<?= View::escape($item['url']) ?>" class="text-slate-600 hover:text-slate-900">
                                <?= View::escape($item['label']) ?>
                            </a>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </nav>
            </div>
            <div class="flex items-center gap-4 text-sm">
                <span class="text-slate-500"><?= View::escape($user->name) ?></span>
                <button type="button"
                        onclick="window.logout()"
                        class="text-slate-600 hover:text-red-600 transition">
                    Salir
                </button>
            </div>
        </div>
    </header>

    <main class="max-w-6xl mx-auto px-4 py-8">
        <?= $content ?? '' ?>
    </main>

    <script>
        window.api = async function (path, options) {
            options = options || {};
            const headers = Object.assign({}, options.headers || {});
            const token = localStorage.getItem('auth_token');
            if (token) headers['Authorization'] = 'Bearer ' + token;
            if (options.body && !(options.body instanceof FormData) && !headers['Content-Type']) {
                headers['Content-Type'] = 'application/json';
            }
            const res = await fetch(path, Object.assign({}, options, { headers, credentials: 'same-origin' }));
            if (res.status === 401) {
                localStorage.removeItem('auth_token');
                document.cookie = 'auth_token=; Path=/; Max-Age=0; SameSite=Lax';
                window.location.href = '/login';
                return null;
            }
            return res;
        };

        window.logout = async function () {
            try { await window.api('/api/auth/logout', { method: 'POST' }); } catch (e) {}
            localStorage.removeItem('auth_token');
            document.cookie = 'auth_token=; Path=/; Max-Age=0; SameSite=Lax';
            window.location.href = '/login';
        };
    </script>
</body>
</html>
