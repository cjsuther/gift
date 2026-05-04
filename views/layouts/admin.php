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
    <header class="bg-white border-b border-slate-200" x-data="{ navOpen: false, userMenuOpen: false }">
        <div class="max-w-6xl mx-auto px-4 py-3 flex items-center justify-between gap-3">
            <div class="flex items-center gap-3 sm:gap-6 min-w-0">
                <a href="<?= View::escape($homeUrl) ?>" class="text-lg font-semibold text-slate-800 shrink-0">Giftcards</a>
                <nav class="hidden md:flex items-center gap-4 text-sm">
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

            <div class="flex items-center gap-2 shrink-0">
                <!-- User dropdown (desktop + mobile) -->
                <div class="relative" @click.outside="userMenuOpen = false">
                    <button type="button" @click="userMenuOpen = !userMenuOpen"
                            class="flex items-center gap-2 px-2 py-1.5 rounded-lg hover:bg-slate-100 transition text-sm">
                        <span class="w-7 h-7 rounded-full bg-slate-800 text-white flex items-center justify-center text-xs font-semibold">
                            <?= View::escape(mb_strtoupper(mb_substr($user->name, 0, 1))) ?>
                        </span>
                        <span class="hidden sm:inline text-slate-700 max-w-[10rem] truncate"><?= View::escape($user->name) ?></span>
                        <svg class="w-4 h-4 text-slate-400 hidden sm:block" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.24 4.5a.75.75 0 01-1.08 0l-4.24-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd"/>
                        </svg>
                    </button>
                    <div x-show="userMenuOpen" x-cloak x-transition.opacity
                         class="absolute right-0 mt-2 w-48 bg-white border border-slate-200 rounded-lg shadow-lg py-1 z-20">
                        <div class="px-3 py-2 text-xs text-slate-500 border-b border-slate-100">
                            <p class="font-medium text-slate-800 truncate"><?= View::escape($user->name) ?></p>
                            <p class="truncate"><?= View::escape($user->email) ?></p>
                        </div>
                        <a href="/perfil" class="block px-3 py-2 text-sm text-slate-700 hover:bg-slate-50">Mi perfil</a>
                        <button type="button" onclick="window.logout()"
                                class="block w-full text-left px-3 py-2 text-sm text-red-600 hover:bg-red-50">
                            Salir
                        </button>
                    </div>
                </div>

                <!-- Hamburger (solo mobile) -->
                <button type="button" @click="navOpen = !navOpen"
                        class="md:hidden p-2 rounded-lg hover:bg-slate-100 transition"
                        aria-label="Abrir menú">
                    <svg x-show="!navOpen" class="w-5 h-5 text-slate-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                    </svg>
                    <svg x-show="navOpen" x-cloak class="w-5 h-5 text-slate-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
        </div>

        <!-- Drawer mobile -->
        <nav x-show="navOpen" x-cloak x-transition
             class="md:hidden border-t border-slate-200 bg-white">
            <div class="px-4 py-2 flex flex-col">
                <?php foreach ($navItems as $item): ?>
                    <?php if (!empty($item['disabled'])): ?>
                        <span class="py-2.5 text-slate-300 cursor-not-allowed text-sm">
                            <?= View::escape($item['label']) ?>
                        </span>
                    <?php else: ?>
                        <a href="<?= View::escape($item['url']) ?>"
                           class="py-2.5 text-slate-700 hover:text-slate-900 text-sm border-b border-slate-100 last:border-0">
                            <?= View::escape($item['label']) ?>
                        </a>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </nav>
    </header>

    <main class="max-w-6xl mx-auto px-4 py-6 sm:py-8">
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
