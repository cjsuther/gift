<?php
/** @var \App\Auth\AuthenticatedUser $user */
/** @var array|null $editing */
/** @var array|null $duplicating */
$isEdit = isset($editing) && $editing !== null;
$dup    = isset($duplicating) && $duplicating !== null;
// En duplicación copiamos sólo título, descripción e imagen; el destinatario y
// el resto quedan vacíos porque la nueva giftcard suele ser para otra persona.
$jsInitial = json_encode([
    'isEdit'            => $isEdit,
    'isDuplicate'       => $dup,
    'id'                => $isEdit ? (int) $editing['id'] : null,
    'copyImageFrom'     => $dup ? (int) $duplicating['id'] : null,
    'title'             => $isEdit ? (string) $editing['title'] : ($dup ? (string) $duplicating['title'] : ''),
    'description'       => $isEdit ? (string) ($editing['description'] ?? '') : ($dup ? (string) ($duplicating['description'] ?? '') : ''),
    'recipient_name'    => $isEdit ? (string) ($editing['recipient_name'] ?? '') : '',
    'recipient_contact' => $isEdit ? (string) ($editing['recipient_contact'] ?? '') : '',
    'sender_name'       => $isEdit ? (string) ($editing['sender_name'] ?? '') : '',
    'sender_email'      => $isEdit ? (string) ($editing['sender_email'] ?? '') : '',
    'expires_at'        => $isEdit ? (string) ($editing['expires_at'] ?? '') : '',
    'image_path'        => $isEdit ? ($editing['image_path'] ?? null) : ($dup ? ($duplicating['image_path'] ?? null) : null),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
?>
<div x-data='giftcardForm(<?= $jsInitial ?>)' class="max-w-2xl mx-auto space-y-6">
    <div>
        <a href="/giftcards" class="text-sm text-slate-500 hover:text-slate-700">← Volver al listado</a>
        <h1 class="text-2xl font-bold text-slate-800 mt-2"
            x-text="isEdit ? 'Editar giftcard' : (isDuplicate ? 'Duplicar giftcard' : 'Nueva giftcard')"></h1>
        <p x-show="isDuplicate" x-cloak class="text-sm text-slate-500 mt-1">
            Copiamos título, descripción e imagen de la giftcard original. Ajustá lo que necesites y creá una nueva.
        </p>
    </div>

    <form @submit.prevent="submit" class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 space-y-5">
        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Título *</label>
            <input type="text" x-model="form.title" required minlength="2" maxlength="150"
                   class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-800 focus:border-transparent outline-none transition"
                   placeholder="Ej: Combo para 2 personas">
        </div>

        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Descripción</label>
            <textarea x-model="form.description" rows="3" maxlength="500"
                      class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-800 focus:border-transparent outline-none transition"
                      placeholder="Detalles del beneficio (lo que ve el cliente al canjear)"></textarea>
        </div>

        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Imagen (JPG/PNG/WebP, máx 2 MB)</label>
            <input type="file" accept="image/jpeg,image/png,image/webp" @change="onImageChange($event)"
                   class="w-full text-sm text-slate-500 file:mr-3 file:py-2 file:px-3 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-slate-100 file:text-slate-700 hover:file:bg-slate-200">
            <template x-if="imagePreview">
                <img :src="imagePreview" alt="Preview" class="mt-3 max-h-48 rounded-lg object-cover border border-slate-200">
            </template>
            <p x-show="isDuplicate && !imageFile && imagePreview" x-cloak class="text-xs text-slate-500 mt-2">
                Se reutilizará esta imagen. Subí un archivo si querés reemplazarla.
            </p>
        </div>

        <div class="grid sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Destinatario (opcional)</label>
                <input type="text" x-model="form.recipient_name" maxlength="150"
                       class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-800 focus:border-transparent outline-none transition"
                       placeholder="Para: Juan Pérez">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Contacto destinatario</label>
                <input type="text" x-model="form.recipient_contact" maxlength="150"
                       class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-800 focus:border-transparent outline-none transition"
                       placeholder="Email, WhatsApp, etc.">
            </div>
        </div>

        <div class="border-t border-slate-200 pt-5 space-y-4">
            <div>
                <h2 class="text-sm font-semibold text-slate-700 uppercase tracking-wide">Quien regala</h2>
                <p class="text-xs text-slate-500 mt-1">Si cargás el email, le avisamos automáticamente cuando la giftcard sea canjeada.</p>
            </div>
            <div class="grid sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Nombre (opcional)</label>
                    <input type="text" x-model="form.sender_name" maxlength="150"
                           class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-800 focus:border-transparent outline-none transition"
                           placeholder="De: María García">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Email (opcional)</label>
                    <input type="email" x-model="form.sender_email" maxlength="150"
                           class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-800 focus:border-transparent outline-none transition"
                           placeholder="maria@ejemplo.com">
                </div>
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Fecha de vencimiento (opcional)</label>
            <input type="date" x-model="form.expires_at"
                   class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-800 focus:border-transparent outline-none transition">
            <p class="text-xs text-slate-500 mt-1">Si no la cargás, la giftcard no vence.</p>
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
            <a href="/giftcards" class="text-sm text-slate-600 hover:text-slate-800">Cancelar</a>
            <button type="submit" :disabled="saving"
                    class="bg-slate-800 hover:bg-slate-900 text-white text-sm font-semibold px-5 py-2 rounded-lg transition disabled:opacity-50 disabled:cursor-not-allowed">
                <span x-show="!saving" x-text="isEdit ? 'Guardar cambios' : 'Crear giftcard'"></span>
                <span x-show="saving" x-cloak>Guardando…</span>
            </button>
        </div>
    </form>
</div>

<script>
    function giftcardForm(initial) {
        return {
            isEdit: !!initial.isEdit,
            isDuplicate: !!initial.isDuplicate,
            id: initial.id,
            copyImageFrom: initial.copyImageFrom,
            form: {
                title: initial.title || '',
                description: initial.description || '',
                recipient_name: initial.recipient_name || '',
                recipient_contact: initial.recipient_contact || '',
                sender_name: initial.sender_name || '',
                sender_email: initial.sender_email || '',
                expires_at: initial.expires_at || '',
            },
            imageFile: null,
            imagePreview: initial.image_path ? ('/uploads/' + initial.image_path) : null,
            saving: false,
            error: '',
            fieldErrors: {},
            onImageChange(ev) {
                const f = ev.target.files && ev.target.files[0];
                if (!f) return;
                this.imageFile = f;
                const r = new FileReader();
                r.onload = e => this.imagePreview = e.target.result;
                r.readAsDataURL(f);
            },
            async submit() {
                this.saving = true;
                this.error = '';
                this.fieldErrors = {};
                try {
                    const fd = new FormData();
                    for (const [k, v] of Object.entries(this.form)) {
                        if (v !== '' && v !== null && v !== undefined) fd.append(k, v);
                    }
                    if (this.imageFile) {
                        fd.append('image', this.imageFile);
                    } else if (this.isDuplicate && this.copyImageFrom) {
                        // Sin archivo nuevo: pedimos al backend clonar la imagen original.
                        fd.append('copy_image_from', this.copyImageFrom);
                    }

                    const url = this.isEdit ? '/api/giftcards/' + this.id : '/api/giftcards';
                    const res = await window.api(url, { method: 'POST', body: fd });
                    if (!res) return;
                    const data = await res.json();
                    if (!res.ok) {
                        this.error = data.error || 'Error al guardar.';
                        if (data.fields) this.fieldErrors = data.fields;
                        return;
                    }
                    const id = data.giftcard?.id || this.id;
                    window.location.href = '/giftcards/' + id;
                } catch (e) {
                    this.error = e.message || 'Error de red.';
                } finally {
                    this.saving = false;
                }
            }
        }
    }
</script>
