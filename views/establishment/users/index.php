<?php
/** @var \App\Auth\AuthenticatedUser $user */
?>
<div x-data="usersList()" x-init="load()" class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-slate-800">Usuarios</h1>
            <p class="text-sm text-slate-500 mt-1">Personas que pueden escanear y canjear giftcards en tu establecimiento.</p>
        </div>
        <a href="/users/new"
           class="inline-flex items-center justify-center bg-slate-800 hover:bg-slate-900 text-white text-sm font-semibold px-4 py-2 rounded-lg transition">
            + Nuevo usuario
        </a>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-4">
        <input type="search"
               x-model.debounce.300ms="search"
               @input="load()"
               placeholder="Buscar por nombre o email…"
               class="w-full sm:w-80 px-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-slate-800 focus:border-transparent outline-none transition">
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
        <template x-if="loading">
            <div class="p-8 text-center text-slate-400 text-sm">Cargando…</div>
        </template>
        <template x-if="!loading && rows.length === 0">
            <div class="p-8 text-center text-slate-400 text-sm">
                <span x-show="search">No se encontraron usuarios para "<span x-text="search"></span>".</span>
                <span x-show="!search">Todavía no hay usuarios. Creá el primero.</span>
            </div>
        </template>
        <template x-if="!loading && rows.length > 0">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-slate-600 text-xs uppercase tracking-wide">
                    <tr>
                        <th class="text-left px-4 py-3">Nombre</th>
                        <th class="text-left px-4 py-3">Email</th>
                        <th class="text-left px-4 py-3 hidden md:table-cell">Último login</th>
                        <th class="text-left px-4 py-3">Estado</th>
                        <th class="text-right px-4 py-3">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <template x-for="row in rows" :key="row.id">
                        <tr>
                            <td class="px-4 py-3 font-medium text-slate-800" x-text="row.name"></td>
                            <td class="px-4 py-3 text-slate-600" x-text="row.email"></td>
                            <td class="px-4 py-3 text-slate-500 hidden md:table-cell" x-text="row.last_login_at || '—'"></td>
                            <td class="px-4 py-3">
                                <span x-show="row.is_active == 1"
                                      class="inline-block px-2 py-0.5 text-xs font-medium bg-emerald-100 text-emerald-700 rounded-full">Activo</span>
                                <span x-show="row.is_active != 1"
                                      class="inline-block px-2 py-0.5 text-xs font-medium bg-slate-100 text-slate-600 rounded-full">Inactivo</span>
                            </td>
                            <td class="px-4 py-3 text-right space-x-3">
                                <a :href="`/users/${row.id}/edit`" class="text-slate-700 hover:text-slate-900 text-sm">Editar</a>
                                <button type="button"
                                        x-show="row.is_active == 1"
                                        @click="deactivate(row)"
                                        class="text-red-600 hover:text-red-700 text-sm">Desactivar</button>
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
    function usersList() {
        return {
            rows: [],
            loading: true,
            error: '',
            search: '',
            async load() {
                this.loading = true;
                this.error = '';
                try {
                    const qs = this.search ? ('?q=' + encodeURIComponent(this.search)) : '';
                    const res = await window.api('/api/users' + qs);
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
                    const res = await window.api('/api/users/' + row.id, { method: 'DELETE' });
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
