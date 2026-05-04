<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ingresar — Giftcards</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body class="bg-slate-100 min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-md bg-white rounded-2xl shadow-lg p-8" x-data="loginForm()">
        <div class="text-center mb-8">
            <h1 class="text-3xl font-bold text-slate-800">Giftcards</h1>
            <p class="text-slate-500 mt-2">Ingresá con tu cuenta</p>
        </div>

        <form @submit.prevent="submit" class="space-y-5">
            <div>
                <label for="email" class="block text-sm font-medium text-slate-700 mb-1">Email</label>
                <input
                    type="email"
                    id="email"
                    x-model="email"
                    autocomplete="email"
                    required
                    class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-800 focus:border-transparent outline-none transition"
                    placeholder="vos@ejemplo.com">
            </div>

            <div>
                <label for="password" class="block text-sm font-medium text-slate-700 mb-1">Contraseña</label>
                <input
                    type="password"
                    id="password"
                    x-model="password"
                    autocomplete="current-password"
                    required
                    class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-800 focus:border-transparent outline-none transition"
                    placeholder="••••••••">
            </div>

            <label class="flex items-center gap-2 text-sm text-slate-600 cursor-pointer">
                <input type="checkbox" x-model="remember"
                       class="rounded border-slate-300 text-slate-800 focus:ring-slate-800">
                <span>Mantenerme conectado en este dispositivo</span>
            </label>

            <div x-show="error" x-cloak class="p-3 bg-red-50 border border-red-200 text-red-700 rounded-lg text-sm" x-text="error"></div>

            <button
                type="submit"
                :disabled="loading"
                class="w-full bg-slate-800 hover:bg-slate-900 text-white font-semibold py-2.5 rounded-lg transition disabled:opacity-50 disabled:cursor-not-allowed">
                <span x-show="!loading">Ingresar</span>
                <span x-show="loading" x-cloak>Ingresando...</span>
            </button>
        </form>

        <p class="text-xs text-slate-400 text-center mt-6">
            Sistema de gestión de giftcards · v1
        </p>
    </div>

    <script>
        function loginForm() {
            return {
                email: '',
                password: '',
                remember: true,
                error: '',
                loading: false,
                async submit() {
                    this.error = '';
                    this.loading = true;
                    try {
                        const res = await fetch('/api/auth/login', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            credentials: 'same-origin',
                            body: JSON.stringify({
                                email: this.email,
                                password: this.password,
                                remember: this.remember,
                            }),
                        });
                        const data = await res.json();
                        if (!res.ok) {
                            this.error = data.error || 'Error al ingresar';
                            return;
                        }
                        // El server ya seteó la cookie HttpOnly via Set-Cookie.
                        // Mantenemos localStorage como fallback para Authorization header
                        // en clientes/contextos donde la cookie no se mande automático.
                        localStorage.setItem('auth_token', data.token);

                        // Si vino con ?next=/algo, ir ahí (post-scan QR, deep link, etc.)
                        const params = new URLSearchParams(window.location.search);
                        const next   = params.get('next');
                        if (next && next.startsWith('/') && !next.startsWith('//')) {
                            window.location.href = next;
                            return;
                        }

                        // Si no, redirigir según rol
                        const role = data.user.role;
                        if (role === 'super_admin')               window.location.href = '/admin/establishments';
                        else if (role === 'establishment_admin')  window.location.href = '/dashboard';
                        else                                      window.location.href = '/scan';
                    } catch (e) {
                        this.error = 'Error de red. Reintentá.';
                    } finally {
                        this.loading = false;
                    }
                }
            }
        }
    </script>
</body>
</html>
