<?php
/** @var \App\Auth\AuthenticatedUser $user */
$canManage = $user->isEstablishmentAdmin();
?>
<div x-data='giftcardsList(<?= (int) $canManage ?>)' x-init="load()" class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-slate-800">Giftcards</h1>
            <p class="text-sm text-slate-500 mt-1">
                <span x-show="canManage">Creá, editá y descargá las giftcards de tu establecimiento.</span>
                <span x-show="!canManage">Listado de giftcards (modo lectura).</span>
            </p>
        </div>
        <a x-show="canManage" href="/giftcards/new"
           class="inline-flex items-center justify-center bg-slate-800 hover:bg-slate-900 text-white text-sm font-semibold px-4 py-2 rounded-lg transition">
            + Nueva giftcard
        </a>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-1 inline-flex flex-wrap gap-1">
        <template x-for="t in tabs" :key="t.value">
            <button type="button" @click="setStatus(t.value)"
                    :class="status === t.value ? 'bg-slate-800 text-white' : 'text-slate-600 hover:bg-slate-100'"
                    class="px-3 py-1.5 rounded-lg text-sm transition flex items-center gap-2">
                <span x-text="t.label"></span>
                <span class="text-xs px-1.5 py-0.5 rounded-full"
                      :class="status === t.value ? 'bg-white/20 text-white' : 'bg-slate-200 text-slate-600'"
                      x-text="counts[t.value] ?? 0"></span>
            </button>
        </template>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-4 grid sm:grid-cols-3 gap-3">
        <input type="search" x-model.debounce.300ms="search" @input="resetAndLoad()"
               placeholder="Buscar por título o destinatario…"
               class="px-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-slate-800 focus:border-transparent outline-none transition">
        <select x-model="assignment" @change="resetAndLoad()"
                class="px-3 py-2 border border-slate-300 rounded-lg text-sm bg-white focus:ring-2 focus:ring-slate-800 focus:border-transparent outline-none transition">
            <option value="">Asignadas y sin asignar</option>
            <option value="unassigned">Sin asignar (sin destinatario)</option>
            <option value="assigned">Asignadas (con destinatario)</option>
        </select>
        <select x-model="sort" @change="resetAndLoad()"
                class="px-3 py-2 border border-slate-300 rounded-lg text-sm bg-white focus:ring-2 focus:ring-slate-800 focus:border-transparent outline-none transition">
            <option value="created_desc">Más recientes primero</option>
            <option value="created_asc">Más antiguas primero</option>
            <option value="redeemed_at">Por fecha de canje</option>
        </select>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
        <template x-if="loading">
            <div class="p-8 text-center text-slate-400 text-sm">Cargando…</div>
        </template>
        <template x-if="!loading && rows.length === 0">
            <div class="p-8 text-center text-slate-400 text-sm">
                <span x-show="search">No se encontraron giftcards para "<span x-text="search"></span>".</span>
                <span x-show="!search && status === 'active' && canManage">No hay giftcards vigentes. Creá la primera.</span>
                <span x-show="!search && (status !== 'active' || !canManage)">No hay giftcards en este estado.</span>
            </div>
        </template>
        <template x-if="!loading && rows.length > 0">
            <div class="overflow-x-auto">
            <table class="w-full text-sm min-w-[720px]">
                <thead class="bg-slate-50 text-slate-600 text-xs uppercase tracking-wide">
                    <tr>
                        <th class="text-left px-4 py-3 w-16"></th>
                        <th class="text-left px-4 py-3">Título</th>
                        <th class="text-left px-4 py-3 hidden md:table-cell">Destinatario</th>
                        <th class="text-left px-4 py-3 hidden md:table-cell">Vence</th>
                        <th class="text-left px-4 py-3">Estado</th>
                        <th class="text-right px-4 py-3">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <template x-for="row in rows" :key="row.id">
                        <tr>
                            <td class="px-4 py-3">
                                <template x-if="row.image_path">
                                    <img :src="`/uploads/${row.image_path}`" :alt="row.title" loading="lazy"
                                         class="w-12 h-12 rounded-lg object-cover border border-slate-200">
                                </template>
                                <template x-if="!row.image_path">
                                    <div class="w-12 h-12 rounded-lg bg-slate-100 flex items-center justify-center text-slate-400 text-xs">—</div>
                                </template>
                            </td>
                            <td class="px-4 py-3">
                                <div class="font-medium text-slate-800" x-text="row.title"></div>
                                <div class="text-xs text-slate-500 mt-0.5" x-text="formatDate(row.created_at)"></div>
                            </td>
                            <td class="px-4 py-3 text-slate-500 hidden md:table-cell">
                                <div x-text="row.recipient_name || '—'"></div>
                                <div class="text-xs text-slate-400" x-text="row.recipient_contact || ''"></div>
                            </td>
                            <td class="px-4 py-3 text-slate-500 hidden md:table-cell" x-text="row.expires_at || 'Sin vencimiento'"></td>
                            <td class="px-4 py-3">
                                <span class="inline-block px-2 py-0.5 text-xs font-medium rounded-full"
                                      :class="statusClass(row.status)" x-text="statusLabel(row.status)"></span>
                            </td>
                            <td class="px-4 py-3 text-right space-x-3 whitespace-nowrap">
                                <a :href="`/giftcards/${row.id}`" class="text-slate-700 hover:text-slate-900 text-sm">Ver</a>
                                <template x-if="canManage && row.status === 'active'">
                                    <a :href="`/giftcards/${row.id}/edit`" class="text-slate-700 hover:text-slate-900 text-sm">Editar</a>
                                </template>
                                <template x-if="canManage">
                                    <a :href="`/api/giftcards/${row.id}/qr`" class="text-slate-700 hover:text-slate-900 text-sm">QR</a>
                                </template>
                                <template x-if="canManage">
                                    <a :href="`/giftcards/${row.id}/duplicate`" class="text-slate-700 hover:text-slate-900 text-sm">Duplicar</a>
                                </template>
                                <template x-if="canManage && row.status === 'active'">
                                    <button type="button" @click="cancel(row)"
                                            class="text-red-600 hover:text-red-700 text-sm">Cancelar</button>
                                </template>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
            </div>
        </template>
    </div>

    <div x-show="totalPages > 1" class="flex items-center justify-between text-sm text-slate-600">
        <span>Página <span x-text="page"></span> de <span x-text="totalPages"></span> · <span x-text="total"></span> en total</span>
        <div class="flex gap-2">
            <button type="button" @click="prevPage()" :disabled="page <= 1"
                    class="px-3 py-1.5 border border-slate-300 rounded-lg disabled:opacity-50 disabled:cursor-not-allowed hover:bg-slate-100">
                ← Anterior
            </button>
            <button type="button" @click="nextPage()" :disabled="page >= totalPages"
                    class="px-3 py-1.5 border border-slate-300 rounded-lg disabled:opacity-50 disabled:cursor-not-allowed hover:bg-slate-100">
                Siguiente →
            </button>
        </div>
    </div>

    <div x-show="error" x-cloak class="p-3 bg-red-50 border border-red-200 text-red-700 rounded-lg text-sm" x-text="error"></div>
</div>

<script>
    function giftcardsList(canManage) {
        return {
            canManage: !!canManage,
            rows: [],
            counts: { active:0, redeemed:0, expired:0, cancelled:0, total:0 },
            total: 0,
            page: 1,
            totalPages: 1,
            loading: true,
            error: '',
            search: '',
            status: 'active',
            assignment: '',
            sort: 'created_desc',
            tabs: [
                { value: 'active',    label: 'Vigentes' },
                { value: 'redeemed',  label: 'Canjeadas' },
                { value: 'expired',   label: 'Vencidas' },
                { value: 'cancelled', label: 'Canceladas' },
                { value: '',          label: 'Todas' },
            ],
            statusLabels: { active:'Vigente', redeemed:'Canjeada', expired:'Vencida', cancelled:'Cancelada' },
            statusClasses: {
                active:    'bg-emerald-100 text-emerald-700',
                redeemed:  'bg-indigo-100 text-indigo-700',
                expired:   'bg-amber-100 text-amber-700',
                cancelled: 'bg-slate-200 text-slate-600',
            },
            statusLabel(s) { return this.statusLabels[s] || s; },
            statusClass(s) { return this.statusClasses[s] || 'bg-slate-100 text-slate-700'; },
            formatDate(s) {
                if (!s) return '';
                const d = new Date(s.replace(' ', 'T'));
                if (isNaN(d)) return s;
                return d.toLocaleDateString('es-AR', { day: '2-digit', month: 'short', year: 'numeric' });
            },
            setStatus(s) { this.status = s; this.page = 1; this.load(); },
            resetAndLoad() { this.page = 1; this.load(); },
            prevPage() { if (this.page > 1) { this.page--; this.load(); } },
            nextPage() { if (this.page < this.totalPages) { this.page++; this.load(); } },
            async load() {
                this.loading = true;
                this.error = '';
                try {
                    const qs = new URLSearchParams({ page: this.page, sort: this.sort });
                    if (this.search) qs.set('q', this.search);
                    if (this.status) qs.set('status', this.status);
                    if (this.assignment) qs.set('assignment', this.assignment);
                    const res = await window.api('/api/giftcards?' + qs.toString());
                    if (!res) return;
                    const data = await res.json();
                    if (!res.ok) throw new Error(data.error || 'Error al cargar.');
                    this.rows = data.items || [];
                    this.total = data.total || 0;
                    this.totalPages = data.total_pages || 1;
                    this.counts = data.counts || this.counts;
                } catch (e) {
                    this.error = e.message || 'Error de red.';
                } finally {
                    this.loading = false;
                }
            },
            async cancel(row) {
                if (!confirm(`¿Cancelar la giftcard "${row.title}"? No se puede deshacer.`)) return;
                try {
                    const res = await window.api('/api/giftcards/' + row.id, { method: 'DELETE' });
                    if (!res) return;
                    const data = await res.json();
                    if (!res.ok) throw new Error(data.error || 'Error al cancelar.');
                    await this.load();
                } catch (e) {
                    this.error = e.message || 'Error de red.';
                }
            }
        }
    }
</script>
