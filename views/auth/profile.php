<?php
/** @var \App\Auth\AuthenticatedUser $user */
/** @var array|null $editing  Datos del usuario incluido establishment_name */
$roleLabels = [
    'super_admin'         => 'Super admin',
    'establishment_admin' => 'Admin de establecimiento',
    'establishment_user'  => 'Usuario operador',
];
$jsInitial = json_encode([
    'name'  => $editing['name']  ?? $user->name,
    'email' => $editing['email'] ?? $user->email,
], JSON_UNESCAPED_UNICODE);
?>
<div x-data='profileForm(<?= $jsInitial ?>)' class="max-w-xl mx-auto space-y-6">
    <div>
        <h1 class="text-2xl font-bold text-slate-800">Mi perfil</h1>
        <p class="text-sm text-slate-500 mt-1">Editá tus datos y cambiá tu contraseña.</p>
    </div>

    <div class="bg-slate-50 border border-slate-200 rounded-xl p-4 text-sm space-y-1">
        <div>
            <span class="text-slate-500">Rol:</span>
            <span class="font-medium text-slate-800"><?= htmlspecialchars($roleLabels[$user->role] ?? $user->role, ENT_QUOTES, 'UTF-8') ?></span>
        </div>
        <?php if (!empty($editing['establishment_name'])): ?>
            <div>
                <span class="text-slate-500">Establecimiento:</span>
                <span class="font-medium text-slate-800"><?= htmlspecialchars($editing['establishment_name'], ENT_QUOTES, 'UTF-8') ?></span>
            </div>
        <?php endif; ?>
    </div>

    <form @submit.prevent="submit" class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 space-y-5">
        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Nombre</label>
            <input type="text" x-model="form.name" minlength="2" maxlength="150" required
                   class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-800 focus:border-transparent outline-none transition">
        </div>

        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Email</label>
            <input type="email" x-model="form.email" required
                   class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-800 focus:border-transparent outline-none transition">
        </div>

        <div class="border-t border-slate-200 pt-5 space-y-4">
            <div>
                <h2 class="text-sm font-semibold text-slate-700 uppercase tracking-wide">Cambiar contraseña</h2>
                <p class="text-xs text-slate-500 mt-1">Dejá los 3 campos vacíos si no querés cambiarla.</p>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Contraseña actual</label>
                <input type="password" x-model="form.current_password" autocomplete="current-password"
                       class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-800 focus:border-transparent outline-none transition">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Nueva contraseña</label>
                <input type="password" x-model="form.new_password" autocomplete="new-password" minlength="8"
                       class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-800 focus:border-transparent outline-none transition"
                       placeholder="Mínimo 8 caracteres">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Confirmar nueva contraseña</label>
                <input type="password" x-model="form.new_password_confirm" autocomplete="new-password"
                       class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-800 focus:border-transparent outline-none transition">
            </div>
        </div>

        <div x-show="error" x-cloak class="p-3 bg-red-50 border border-red-200 text-red-700 rounded-lg text-sm" x-text="error"></div>
        <div x-show="success" x-cloak class="p-3 bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-lg text-sm" x-text="success"></div>
        <div x-show="fieldErrors && Object.keys(fieldErrors).length > 0" x-cloak class="p-3 bg-amber-50 border border-amber-200 text-amber-800 rounded-lg text-sm">
            <ul class="space-y-1">
                <template x-for="(msg, field) in fieldErrors" :key="field">
                    <li><strong x-text="field"></strong>: <span x-text="msg"></span></li>
                </template>
            </ul>
        </div>

        <div class="flex items-center justify-end gap-3 pt-2">
            <button type="submit" :disabled="saving"
                    class="bg-slate-800 hover:bg-slate-900 text-white text-sm font-semibold px-5 py-2 rounded-lg transition disabled:opacity-50 disabled:cursor-not-allowed">
                <span x-show="!saving">Guardar cambios</span>
                <span x-show="saving" x-cloak>Guardando…</span>
            </button>
        </div>
    </form>
</div>

<script>
    function profileForm(initial) {
        return {
            form: {
                name: initial.name || '',
                email: initial.email || '',
                current_password: '',
                new_password: '',
                new_password_confirm: '',
            },
            saving: false,
            error: '',
            success: '',
            fieldErrors: {},
            async submit() {
                this.saving = true;
                this.error = '';
                this.success = '';
                this.fieldErrors = {};
                try {
                    const payload = {
                        name: this.form.name,
                        email: this.form.email,
                    };
                    if (this.form.new_password) {
                        payload.current_password     = this.form.current_password;
                        payload.new_password         = this.form.new_password;
                        payload.new_password_confirm = this.form.new_password_confirm;
                    }
                    const res = await window.api('/api/perfil', {
                        method: 'PUT',
                        body: JSON.stringify(payload),
                    });
                    if (!res) return;
                    const data = await res.json();
                    if (!res.ok) {
                        this.error = data.error || 'Error al guardar.';
                        if (data.fields) this.fieldErrors = data.fields;
                        return;
                    }
                    this.success = 'Cambios guardados.';
                    this.form.current_password = '';
                    this.form.new_password = '';
                    this.form.new_password_confirm = '';
                    // Si cambió el email, hay que reloguearse para que el JWT refleje el cambio
                    // (el JWT actual sigue siendo válido por el id, pero el header del UI mostraría
                    // el nombre viejo hasta que se refresque la página).
                    setTimeout(() => window.location.reload(), 800);
                } catch (e) {
                    this.error = e.message || 'Error de red.';
                } finally {
                    this.saving = false;
                }
            }
        }
    }
</script>
