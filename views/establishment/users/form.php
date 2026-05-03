<?php
/** @var \App\Auth\AuthenticatedUser $user */
/** @var array|null $editing */
$isEdit = isset($editing) && $editing !== null;
$jsInitial = json_encode([
    'isEdit' => $isEdit,
    'id'     => $isEdit ? (int) $editing['id'] : null,
    'name'   => $isEdit ? (string) $editing['name'] : '',
    'email'  => $isEdit ? (string) $editing['email'] : '',
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
?>
<div x-data='userForm(<?= $jsInitial ?>)' class="max-w-xl mx-auto space-y-6">
    <div>
        <a href="/users" class="text-sm text-slate-500 hover:text-slate-700">← Volver</a>
        <h1 class="text-2xl font-bold text-slate-800 mt-2"
            x-text="isEdit ? 'Editar usuario' : 'Nuevo usuario'"></h1>
    </div>

    <form @submit.prevent="submit" class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 space-y-5">
        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Nombre *</label>
            <input type="text" x-model="form.name" required minlength="2" maxlength="150"
                   class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-800 focus:border-transparent outline-none transition">
        </div>

        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Email *</label>
            <input type="email" x-model="form.email" required
                   class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-800 focus:border-transparent outline-none transition">
        </div>

        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">
                <span x-show="!isEdit">Contraseña inicial *</span>
                <span x-show="isEdit">Nueva contraseña</span>
            </label>
            <input type="text" x-model="form.password" :required="!isEdit" minlength="8"
                   class="w-full px-3 py-2 border border-slate-300 rounded-lg font-mono text-sm focus:ring-2 focus:ring-slate-800 focus:border-transparent outline-none transition"
                   :placeholder="isEdit ? 'Dejar vacío para no cambiar' : 'Mínimo 8 caracteres'">
            <p class="text-xs text-slate-500 mt-1">
                <span x-show="!isEdit">Pasásela al usuario en persona o por canal seguro.</span>
                <span x-show="isEdit">Solo se actualiza si escribís algo. Vacío = no cambia.</span>
            </p>
        </div>

        <div x-show="isEdit">
            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" x-model="form.is_active"
                       class="rounded border-slate-300 text-slate-800 focus:ring-slate-800">
                <span>Usuario activo</span>
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
            <a href="/users" class="text-sm text-slate-600 hover:text-slate-800">Cancelar</a>
            <button type="submit" :disabled="saving"
                    class="bg-slate-800 hover:bg-slate-900 text-white text-sm font-semibold px-5 py-2 rounded-lg transition disabled:opacity-50 disabled:cursor-not-allowed">
                <span x-show="!saving" x-text="isEdit ? 'Guardar cambios' : 'Crear usuario'"></span>
                <span x-show="saving" x-cloak>Guardando…</span>
            </button>
        </div>
    </form>
</div>

<script>
    function userForm(initial) {
        return {
            isEdit: !!initial.isEdit,
            id: initial.id,
            form: {
                name: initial.name || '',
                email: initial.email || '',
                password: '',
                is_active: true,
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
                    };
                    if (this.form.password) payload.password = this.form.password;
                    if (this.isEdit) payload.is_active = this.form.is_active ? 1 : 0;

                    const url = this.isEdit ? '/api/users/' + this.id : '/api/users';
                    const method = this.isEdit ? 'PUT' : 'POST';
                    const res = await window.api(url, {
                        method,
                        body: JSON.stringify(payload),
                    });
                    if (!res) return;
                    const data = await res.json();
                    if (!res.ok) {
                        this.error = data.error || 'Error al guardar.';
                        if (data.fields) this.fieldErrors = data.fields;
                        return;
                    }
                    window.location.href = '/users';
                } catch (e) {
                    this.error = e.message || 'Error de red.';
                } finally {
                    this.saving = false;
                }
            }
        }
    }
</script>
