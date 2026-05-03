<?php
/** @var \App\Auth\AuthenticatedUser $user */
?>
<div x-data="adminUsersList(<?= (int) $user->id ?>)" x-init="init()" class="space-y-6">
    <div>
        <h1 class="text-2xl font-bold text-slate-800">Usuarios del sistema</h1>
        <p class="text-sm text-slate-500 mt-1">Todos los usuarios — super admins, admins y operadores de cada establecimiento.</p>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-4 grid sm:grid-cols-4 gap-3">
        <input type="search" x-model.debounce.300ms="filters.q" @input="load()"
               placeholder="Buscar nombre o email…"
               class="px-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-slate-800 focus:border-transparent outline-none transition">
        <select x-model="filters.role" @change="load()"
                class="px-3 py-2 border border-slate-300 rounded-lg text-sm bg-white focus:ring-2 focus:ring-slate-800 focus:border-transparent outline-none transition">
            <option value="">Todos los roles</option>
            <option value="super_admin">Super admin</option>
            <option value="establishment_admin">Admin de establecimiento</option>
            <option value="establishment_user">Usuario operador</option>
        </select>
        <select x-model="filters.establishment_id" @change="load()"
                class="px-3 py-2 border border-slate-300 rounded-lg text-sm bg-white focus:ring-2 focus:ring-slate-800 focus:border-transparent outline-none transition">
            <option value="">Todos los establecimientos</option>
            <template x-for="e in establishments" :key="e.id">
                <option :value="e.id" x-text="e.name"></option>
            </template>
        </select>
        <select x-model="filters.is_active" @change="load()"
                class="px-3 py-2 border border-slate-300 rounded-lg text-sm bg-white focus:ring-2 focus:ring-slate-800 focus:border-transparent outline-none transition">
            <option value="">Activos e inactivos</option>
            <option value="1">Solo activos</option>
            <option value="0">Solo inactivos</option>
        </select>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
        <template x-if="loading">
            <div class="p-8 text-center text-slate-400 text-sm">Cargando…</div>
        </template>
        <template x-if="!loading && rows.length === 0">
            <div class="p-8 text-center text-slate-400 text-sm">No se encontraron usuarios para los filtros aplicados.</div>
        </template>
        <template x-if="!loading && rows.length > 0">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-slate-600 text-xs uppercase tracking-wide">
                    <tr>
                        <th class="text-left px-4 py-3">Nombre</th>
                        <th class="text-left px-4 py-3">Email</th>
                        <th class="text-left px-4 py-3 hidden md:table-cell">Establecimiento</th>
                        <th class="text-left px-4 py-3">Rol</th>
                        <th class="text-left px-4 py-3">Estado</th>
                        <th class="text-right px-4 py-3">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <template x-for="row in rows" :key="row.id">
                        <tr>
                            <td class="px-4 py-3 font-medium text-slate-800">
                                <span x-text="row.name"></span>
                                <span x-show="row.id == currentUserId" class="text-xs text-slate-400 ml-1">(vos)</span>
                            </td>
                            <td class="px-4 py-3 text-slate-600" x-text="row.email"></td>
                            <td class="px-4 py-3 text-slate-500 hidden md:table-cell" x-text="row.establishment_name || '—'"></td>
                            <td class="px-4 py-3">
                                <span class="inline-block px-2 py-0.5 text-xs font-medium rounded-full"
                                      :class="roleClass(row.role)" x-text="roleLabel(row.role)"></span>
                            </td>
                            <td class="px-4 py-3">
                                <span x-show="row.is_active == 1"
                                      class="inline-block px-2 py-0.5 text-xs font-medium bg-emerald-100 text-emerald-700 rounded-full">Activo</span>
                                <span x-show="row.is_active != 1"
                                      class="inline-block px-2 py-0.5 text-xs font-medium bg-slate-100 text-slate-600 rounded-full">Inactivo</span>
                            </td>
                            <td class="px-4 py-3 text-right space-x-3">
                                <template x-if="canEdit(row)">
                                    <a :href="`/admin/users/${row.id}/edit`" class="text-slate-700 hover:text-slate-900 text-sm">Editar</a>
                                </template>
                                <template x-if="!canEdit(row)">
                                    <span class="text-slate-300 text-sm cursor-not-allowed" title="No editable: otro super admin">—</span>
                                </template>
                                <template x-if="canDeactivate(row)">
                                    <button type="button" @click="deactivate(row)"
                                            class="text-red-600 hover:text-red-700 text-sm">Desactivar</button>
                                </template>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </template>
    </div>

    <div x-show="error" x-cloak class="p-3 bg-red-50 border border-red-200 text-red-700 rounded-lg text-sm" x-text="error"></div>
</div>

<script>
    function adminUsersList(currentUserId) {
        return {
            currentUserId,
            rows: [],
            establishments: [],
            loading: true,
            error: '',
            filters: { q: '', role: '', establishment_id: '', is_active: '' },
            roleLabels: {
                super_admin: 'Super admin',
                establishment_admin: 'Admin estab.',
                establishment_user: 'Usuario',
            },
            roleClasses: {
                super_admin:         'bg-purple-100 text-purple-700',
                establishment_admin: 'bg-indigo-100 text-indigo-700',
                establishment_user:  'bg-slate-100 text-slate-700',
            },
            roleLabel(r) { return this.roleLabels[r] || r; },
            roleClass(r) { return this.roleClasses[r] || 'bg-slate-100 text-slate-700'; },
            canEdit(row) {
                // Otros super admins son read-only por seguridad. Yo mismo sí puedo editarme.
                if (row.role !== 'super_admin') return true;
                return row.id == this.currentUserId;
            },
            canDeactivate(row) {
                if (row.is_active != 1) return false;
                if (row.id == this.currentUserId) return false;
                if (row.role === 'super_admin') return false;
                return true;
            },
            async init() {
                await this.loadEstablishments();
                await this.load();
            },
            async loadEstablishments() {
                try {
                    const res = await window.api('/api/admin/establishments');
                    if (!res) return;
                    const data = await res.json();
                    if (res.ok) this.establishments = data.establishments || [];
                } catch (e) { /* silently */ }
            },
            async load() {
                this.loading = true;
                this.error = '';
                try {
                    const qs = new URLSearchParams();
                    for (const [k, v] of Object.entries(this.filters)) {
                        if (v !== '' && v !== null) qs.set(k, v);
                    }
                    const url = '/api/admin/users' + (qs.toString() ? '?' + qs.toString() : '');
                    const res = await window.api(url);
                    if (!res) return;
                    const data = await res.json();
                    if (!res.ok) throw new Error(data.error || 'Error al cargar.');
                    this.rows = data.users || [];
                } catch (e) {
                    this.error = e.message || 'Error de red.';
                } finally {
                    this.loading = false;
                }
            },
            async deactivate(row) {
                if (!confirm(`¿Desactivar a "${row.name}"? No va a poder volver a ingresar.`)) return;
                try {
                    const res = await window.api('/api/admin/users/' + row.id, { method: 'DELETE' });
                    if (!res) return;
                    const data = await res.json();
                    if (!res.ok) throw new Error(data.error || 'Error al desactivar.');
                    await this.load();
                } catch (e) {
                    this.error = e.message || 'Error de red.';
                }
            }
        }
    }
</script>
