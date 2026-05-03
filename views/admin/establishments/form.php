<?php
/** @var \App\Auth\AuthenticatedUser $user */
/** @var array|null $establishment */
$isEdit = isset($establishment) && $establishment !== null;
$jsInitial = json_encode([
    'isEdit'        => $isEdit,
    'id'            => $isEdit ? (int) $establishment['id'] : null,
    'name'          => $isEdit ? (string) $establishment['name'] : '',
    'address'       => $isEdit ? (string) ($establishment['address'] ?? '') : '',
    'phone'         => $isEdit ? (string) ($establishment['phone'] ?? '') : '',
    'email'         => $isEdit ? (string) ($establishment['email'] ?? '') : '',
    'primary_color' => $isEdit ? (string) ($establishment['primary_color'] ?? '#111827') : '#111827',
    'logo_path'     => $isEdit ? ($establishment['logo_path'] ?? null) : null,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
?>
<div x-data='establishmentForm(<?= $jsInitial ?>)' class="max-w-2xl mx-auto space-y-6">
    <div>
        <a href="/admin/establishments" class="text-sm text-slate-500 hover:text-slate-700">← Volver</a>
        <h1 class="text-2xl font-bold text-slate-800 mt-2" x-text="isEdit ? 'Editar establecimiento' : 'Nuevo establecimiento'"></h1>
    </div>

    <form @submit.prevent="submit" class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 space-y-5">
        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Nombre *</label>
            <input type="text" x-model="form.name" required minlength="2" maxlength="150"
                   class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-800 focus:border-transparent outline-none transition">
        </div>

        <div class="grid sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Email de contacto</label>
                <input type="email" x-model="form.email"
                       class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-800 focus:border-transparent outline-none transition">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Teléfono</label>
                <input type="text" x-model="form.phone"
                       class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-800 focus:border-transparent outline-none transition">
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Dirección</label>
            <input type="text" x-model="form.address"
                   class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-800 focus:border-transparent outline-none transition">
        </div>

        <div class="grid sm:grid-cols-2 gap-4 items-end">
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Color primario</label>
                <div class="flex items-center gap-3">
                    <input type="color" x-model="form.primary_color"
                           class="h-10 w-14 border border-slate-300 rounded-lg cursor-pointer">
                    <input type="text" x-model="form.primary_color" pattern="^#[0-9a-fA-F]{6}$"
                           class="flex-1 px-3 py-2 border border-slate-300 rounded-lg font-mono text-sm focus:ring-2 focus:ring-slate-800 focus:border-transparent outline-none transition">
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Logo (JPG/PNG/WebP, máx 2 MB)</label>
                <input type="file" accept="image/jpeg,image/png,image/webp" @change="onLogoChange($event)"
                       class="w-full text-sm text-slate-500 file:mr-3 file:py-2 file:px-3 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-slate-100 file:text-slate-700 hover:file:bg-slate-200">
                <template x-if="logoPreview">
                    <img :src="logoPreview" alt="Logo" class="mt-3 w-16 h-16 rounded-lg object-cover border border-slate-200">
                </template>
            </div>
        </div>

        <template x-if="!isEdit">
            <div class="border-t border-slate-200 pt-5 space-y-5">
                <h2 class="text-sm font-semibold text-slate-700 uppercase tracking-wide">Administrador inicial</h2>
                <p class="text-xs text-slate-500 -mt-3">Este usuario va a poder gestionar las giftcards y los usuarios del establecimiento.</p>

                <div class="grid sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Nombre *</label>
                        <input type="text" x-model="form.admin_name" :required="!isEdit" minlength="2"
                               class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-800 focus:border-transparent outline-none transition">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Email *</label>
                        <input type="email" x-model="form.admin_email" :required="!isEdit"
                               class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-800 focus:border-transparent outline-none transition">
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Contraseña inicial *</label>
                    <input type="text" x-model="form.admin_password" :required="!isEdit" minlength="8"
                           class="w-full px-3 py-2 border border-slate-300 rounded-lg font-mono text-sm focus:ring-2 focus:ring-slate-800 focus:border-transparent outline-none transition"
                           placeholder="Mínimo 8 caracteres">
                    <p class="text-xs text-slate-500 mt-1">Se la pasás al admin del establecimiento. Recomendale cambiarla en el primer login (próxima fase).</p>
                </div>
            </div>
        </template>

        <div x-show="error" x-cloak class="p-3 bg-red-50 border border-red-200 text-red-700 rounded-lg text-sm" x-text="error"></div>
        <div x-show="fieldErrors && Object.keys(fieldErrors).length > 0" x-cloak class="p-3 bg-amber-50 border border-amber-200 text-amber-800 rounded-lg text-sm">
            <ul class="space-y-1">
                <template x-for="(msg, field) in fieldErrors" :key="field">
                    <li><strong x-text="field"></strong>: <span x-text="msg"></span></li>
                </template>
            </ul>
        </div>

        <div class="flex items-center justify-end gap-3 pt-2">
            <a href="/admin/establishments" class="text-sm text-slate-600 hover:text-slate-800">Cancelar</a>
            <button type="submit" :disabled="saving"
                    class="bg-slate-800 hover:bg-slate-900 text-white text-sm font-semibold px-5 py-2 rounded-lg transition disabled:opacity-50 disabled:cursor-not-allowed">
                <span x-show="!saving" x-text="isEdit ? 'Guardar cambios' : 'Crear establecimiento'"></span>
                <span x-show="saving" x-cloak>Guardando…</span>
            </button>
        </div>
    </form>
</div>

<script>
    function establishmentForm(initial) {
        return {
            isEdit: !!initial.isEdit,
            id: initial.id,
            form: {
                name: initial.name || '',
                address: initial.address || '',
                phone: initial.phone || '',
                email: initial.email || '',
                primary_color: initial.primary_color || '#111827',
                admin_name: '',
                admin_email: '',
                admin_password: '',
            },
            logoFile: null,
            logoPreview: initial.logo_path ? ('/uploads/' + initial.logo_path) : null,
            saving: false,
            error: '',
            fieldErrors: {},
            onLogoChange(ev) {
                const f = ev.target.files && ev.target.files[0];
                if (!f) { this.logoFile = null; return; }
                this.logoFile = f;
                const r = new FileReader();
                r.onload = e => this.logoPreview = e.target.result;
                r.readAsDataURL(f);
            },
            async submit() {
                this.saving = true;
                this.error = '';
                this.fieldErrors = {};
                try {
                    const fd = new FormData();
                    for (const [k, v] of Object.entries(this.form)) {
                        if (this.isEdit && k.startsWith('admin_')) continue;
                        if (v !== '' && v !== null && v !== undefined) fd.append(k, v);
                    }
                    if (this.logoFile) fd.append('logo', this.logoFile);

                    // Siempre POST: PHP SAPI no parsea multipart en PUT.
                    // El backend registra POST y PUT sobre /api/admin/establishments/{id}.
                    const url = this.isEdit ? '/api/admin/establishments/' + this.id : '/api/admin/establishments';
                    const res = await window.api(url, { method: 'POST', body: fd });
                    if (!res) return;
                    const data = await res.json();
                    if (!res.ok) {
                        this.error = data.error || 'Error al guardar.';
                        if (data.fields) this.fieldErrors = data.fields;
                        return;
                    }
                    window.location.href = '/admin/establishments';
                } catch (e) {
                    this.error = e.message || 'Error de red.';
                } finally {
                    this.saving = false;
                }
            }
        }
    }
</script>
