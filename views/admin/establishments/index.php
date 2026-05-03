<?php
/** @var \App\Auth\AuthenticatedUser $user */
?>
<div x-data="establishmentsList()" x-init="load()" class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-slate-800">Establecimientos</h1>
            <p class="text-sm text-slate-500 mt-1">Gestioná los establecimientos de la plataforma.</p>
        </div>
        <a href="/admin/establishments/new"
           class="inline-flex items-center justify-center bg-slate-800 hover:bg-slate-900 text-white text-sm font-semibold px-4 py-2 rounded-lg transition">
            + Nuevo establecimiento
        </a>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-4">
        <input type="search"
               x-model.debounce.300ms="search"
               @input="load()"
               placeholder="Buscar por nombre o slug…"
               class="w-full sm:w-80 px-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-slate-800 focus:border-transparent outline-none transition">
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
        <template x-if="loading">
            <div class="p-8 text-center text-slate-400 text-sm">Cargando…</div>
        </template>

        <template x-if="!loading && rows.length === 0">
            <div class="p-8 text-center text-slate-400 text-sm">
                <span x-show="search">No se encontraron establecimientos para "<span x-text="search"></span>".</span>
                <span x-show="!search">Todavía no hay establecimientos. Creá el primero.</span>
            </div>
        </template>

        <template x-if="!loading && rows.length > 0">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-slate-600 text-xs uppercase tracking-wide">
                    <tr>
                        <th class="text-left px-4 py-3 w-12">Logo</th>
                        <th class="text-left px-4 py-3">Nombre</th>
                        <th class="text-left px-4 py-3 hidden md:table-cell">Slug</th>
                        <th class="text-left px-4 py-3 hidden md:table-cell">Contacto</th>
                        <th class="text-left px-4 py-3">Estado</th>
                        <th class="text-right px-4 py-3">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <template x-for="row in rows" :key="row.id">
                        <tr>
                            <td class="px-4 py-3">
                                <div class="w-9 h-9 rounded-lg flex items-center justify-center text-xs font-semibold text-white"
                                     :style="`background-color:${row.primary_color || '#111827'}`">
                                    <template x-if="row.logo_path">
                                        <img :src="`/uploads/${row.logo_path}`" :alt="row.name" class="w-9 h-9 rounded-lg object-cover">
                                    </template>
                                    <template x-if="!row.logo_path">
                                        <span x-text="row.name.substring(0, 2).toUpperCase()"></span>
                                    </template>
                                </div>
                            </td>
                            <td class="px-4 py-3 font-medium text-slate-800" x-text="row.name"></td>
                            <td class="px-4 py-3 text-slate-500 hidden md:table-cell" x-text="row.slug"></td>
                            <td class="px-4 py-3 text-slate-500 hidden md:table-cell">
                                <div x-text="row.email || '—'"></div>
                                <div class="text-xs text-slate-400" x-text="row.phone || ''"></div>
                            </td>
                            <td class="px-4 py-3">
                                <span x-show="row.is_active == 1"
                                      class="inline-block px-2 py-0.5 text-xs font-medium bg-emerald-100 text-emerald-700 rounded-full">
                                    Activo
                                </span>
                                <span x-show="row.is_active != 1"
                                      class="inline-block px-2 py-0.5 text-xs font-medium bg-slate-100 text-slate-600 rounded-full">
                                    Inactivo
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right space-x-3">
                                <a :href="`/admin/establishments/${row.id}/edit`"
                                   class="text-slate-700 hover:text-slate-900 text-sm">Editar</a>
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
    function establishmentsList() {
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
                    const res = await window.api('/api/admin/establishments' + qs);
                    if (!res) return;
                    const data = await res.json();
                    if (!res.ok) throw new Error(data.error || 'Error al cargar.');
                    this.rows = data.establishments || [];
                } catch (e) {
                    this.error = e.message || 'Error de red.';
                } finally {
                    this.loading = false;
                }
            },
            async deactivate(row) {
                if (!confirm(`¿Desactivar "${row.name}"? Los usuarios del establecimiento dejarán de poder ingresar.`)) return;
                try {
                    const res = await window.api('/api/admin/establishments/' + row.id, { method: 'DELETE' });
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
