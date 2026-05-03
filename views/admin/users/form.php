<?php
/** @var \App\Auth\AuthenticatedUser $user */
/** @var array $editing */
$isSelf = ((int) $user->id) === ((int) $editing['id']);
$jsInitial = json_encode([
    'id'                  => (int) $editing['id'],
    'name'                => (string) $editing['name'],
    'email'               => (string) $editing['email'],
    'role'                => (string) $editing['role'],
    'is_active'           => (int) $editing['is_active'] === 1,
    'establishment_name'  => $editing['establishment_name'] ?? null,
    'isSelf'              => $isSelf,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

$roleLabels = [
    'super_admin'         => 'Super admin',
    'establishment_admin' => 'Admin de establecimiento',
    'establishment_user'  => 'Usuario operador',
];
?>
<div x-data='adminUserForm(<?= $jsInitial ?>)' class="max-w-xl mx-auto space-y-6">
    <div>
        <a href="/admin/users" class="text-sm text-slate-500 hover:text-slate-700">← Volver</a>
        <h1 class="text-2xl font-bold text-slate-800 mt-2">Editar usuario</h1>
    </div>

    <div class="bg-slate-50 border border-slate-200 rounded-xl p-4 text-sm space-y-1">
        <div><span class="text-slate-500">Rol:</span>
            <span class="font-medium text-slate-800"><?= $roleLabels[$editing['role']] ?? $editing['role'] ?></span>
            <span class="text-xs text-slate-400 ml-2">(no editable desde acá)</span>
        </div>
        <div x-show="establishment_name">
            <span class="text-slate-500">Establecimiento:</span>
            <span class="font-medium text-slate-800" x-text="establishment_name"></span>
        </div>
        <div x-show="isSelf" class="text-slate-600">Estás editando tu propia cuenta.</div>
    </div>

    <form @submit.prevent="submit" class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 space-y-5">
        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Nombre</label>
            <input type="text" x-model="form.name" minlength="2" maxlength="150"
                   class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-800 focus:border-transparent outline-none transition">
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Email</label>
            <input type="email" x-model="form.email"
                   class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-800 focus:border-transparent outline-none transition">
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Nueva contraseña</label>
            <input type="text" x-model="form.password" minlength="8"
                   class="w-full px-3 py-2 border border-slate-300 rounded-lg font-mono text-sm focus:ring-2 focus:ring-slate-800 focus:border-transparent outline-none transition"
                   placeholder="Dejar vacío para no cambiar">
            <p class="text-xs text-slate-500 mt-1">Solo se actualiza si escribís algo. Vacío = no cambia.</p>
        </div>
        <div>
            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" x-model="form.is_active" :disabled="isSelf"
                       class="rounded border-slate-300 text-slate-800 focus:ring-slate-800 disabled:opacity-50">
                <span>Usuario activo</span>
                <span x-show="isSelf" class="text-xs text-slate-400 ml-2">(no podés desactivarte a vos mismo)</span>
            </label>
        </div>

        <div x-show="error" x-cloak class="p-3 bg-red-50 border border-red-200 text-red-700 rounded-lg text-sm" x-text="error"></div>
        <div x-show="fieldErrors && Object.keys(fieldErrors).length > 0" x-cloak class="p-3 bg-amber-50 border border-amber-200 text-amber-800 rounded-lg text-sm">
            <ul class="space-y-1">
                <template x-for="(msg, field) in fieldErrors" :key="field">
                    <li><strong x-text="field"></strong>: <span x-text="msg"></span></li>
                </template>
            </ul>
        </div>

        <div class="flex items-center justify-end gap-3 pt-2">
            <a href="/admin/users" class="text-sm text-slate-600 hover:text-slate-800">Cancelar</a>
            <button type="submit" :disabled="saving"
                    class="bg-slate-800 hover:bg-slate-900 text-white text-sm font-semibold px-5 py-2 rounded-lg transition disabled:opacity-50 disabled:cursor-not-allowed">
                <span x-show="!saving">Guardar cambios</span>
                <span x-show="saving" x-cloak>Guardando…</span>
            </button>
        </div>
    </form>
</div>

<script>
    function adminUserForm(initial) {
        return {
            id: initial.id,
            isSelf: !!initial.isSelf,
            establishment_name: initial.establishment_name,
            form: {
                name: initial.name,
                email: initial.email,
                password: '',
                is_active: !!initial.is_active,
            },
            saving: false,
            error: '',
            fieldErrors: {},
            async submit() {
                this.saving = true;
                this.error = '';
                this.fieldErrors = {};
                try {
                    const payload = {
                        name: this.form.name,
                        email: this.form.email,
                        is_active: this.form.is_active ? 1 : 0,
                    };
                    if (this.form.password) payload.password = this.form.password;

                    const res = await window.api('/api/admin/users/' + this.id, {
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
                    window.location.href = '/admin/users';
                } catch (e) {
                    this.error = e.message || 'Error de red.';
                } finally {
                    this.saving = false;
                }
            }
        }
    }
</script>
