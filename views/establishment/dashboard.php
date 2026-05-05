<?php
/** @var \App\Auth\AuthenticatedUser $user */
?>
<div x-data="dashboardPage()" x-init="load()" class="space-y-6">
    <div>
        <h1 class="text-2xl font-bold text-slate-800">Hola, <?= htmlspecialchars($user->name) ?>.</h1>
        <p class="text-sm text-slate-500 mt-1">Resumen de tu establecimiento.</p>
    </div>

    <template x-if="loading">
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-8 text-center text-slate-400 text-sm">
            Cargando datos…
        </div>
    </template>

    <template x-if="!loading && stats">
        <div class="space-y-6">
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-4">
                    <p class="text-xs text-slate-500 uppercase tracking-wide">Total emitidas</p>
                    <p class="text-2xl font-bold text-slate-800 mt-1" x-text="stats.counts.total"></p>
                </div>
                <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-4">
                    <p class="text-xs text-emerald-600 uppercase tracking-wide font-medium">Vigentes</p>
                    <p class="text-2xl font-bold text-emerald-700 mt-1" x-text="stats.counts.active"></p>
                </div>
                <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-4">
                    <p class="text-xs text-indigo-600 uppercase tracking-wide font-medium">Canjeadas (este mes)</p>
                    <p class="text-2xl font-bold text-indigo-700 mt-1" x-text="stats.redeemed_this_month"></p>
                </div>
                <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-4">
                    <p class="text-xs text-slate-500 uppercase tracking-wide">% canje histórico</p>
                    <p class="text-2xl font-bold text-slate-800 mt-1">
                        <span x-text="stats.redemption_rate"></span><span class="text-base text-slate-400">%</span>
                    </p>
                </div>
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                <div class="bg-slate-50 rounded-xl border border-slate-200 p-3 text-center">
                    <p class="text-xs text-slate-500">Vigentes</p>
                    <p class="text-lg font-bold text-emerald-700" x-text="stats.counts.active"></p>
                </div>
                <div class="bg-slate-50 rounded-xl border border-slate-200 p-3 text-center">
                    <p class="text-xs text-slate-500">Canjeadas</p>
                    <p class="text-lg font-bold text-indigo-700" x-text="stats.counts.redeemed"></p>
                </div>
                <div class="bg-slate-50 rounded-xl border border-slate-200 p-3 text-center">
                    <p class="text-xs text-slate-500">Vencidas</p>
                    <p class="text-lg font-bold text-amber-700" x-text="stats.counts.expired"></p>
                </div>
                <div class="bg-slate-50 rounded-xl border border-slate-200 p-3 text-center">
                    <p class="text-xs text-slate-500">Canceladas</p>
                    <p class="text-lg font-bold text-slate-600" x-text="stats.counts.cancelled"></p>
                </div>
            </div>

            <div class="grid lg:grid-cols-2 gap-6">
                <div class="bg-white rounded-xl shadow-sm border border-slate-200">
                    <div class="px-5 py-3 border-b border-slate-200 flex items-center justify-between">
                        <h2 class="font-semibold text-slate-800">Últimas creadas</h2>
                        <a href="/giftcards" class="text-xs text-slate-500 hover:text-slate-800">Ver todas →</a>
                    </div>
                    <template x-if="recentCreated.length === 0">
                        <div class="p-5 text-sm text-slate-400 text-center">No hay giftcards todavía.</div>
                    </template>
                    <ul class="divide-y divide-slate-100">
                        <template x-for="g in recentCreated" :key="g.id">
                            <li>
                                <a :href="`/giftcards/${g.id}`" class="flex items-center gap-3 p-3 hover:bg-slate-50 transition">
                                    <template x-if="g.image_path">
                                        <img :src="`/uploads/${g.image_path}`" loading="lazy" class="w-10 h-10 rounded-lg object-cover border border-slate-200">
                                    </template>
                                    <template x-if="!g.image_path">
                                        <div class="w-10 h-10 rounded-lg bg-slate-100 flex items-center justify-center text-xs text-slate-400">—</div>
                                    </template>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-sm font-medium text-slate-800 truncate" x-text="g.title"></p>
                                        <p class="text-xs text-slate-500" x-text="g.recipient_name || 'Sin destinatario'"></p>
                                    </div>
                                    <span class="text-xs px-2 py-0.5 rounded-full"
                                          :class="statusClass(g.status)" x-text="statusLabel(g.status)"></span>
                                </a>
                            </li>
                        </template>
                    </ul>
                </div>

                <div class="bg-white rounded-xl shadow-sm border border-slate-200">
                    <div class="px-5 py-3 border-b border-slate-200 flex items-center justify-between">
                        <h2 class="font-semibold text-slate-800">Últimas canjeadas</h2>
                        <a href="/giftcards?status=redeemed" class="text-xs text-slate-500 hover:text-slate-800">Ver todas →</a>
                    </div>
                    <template x-if="recentRedeemed.length === 0">
                        <div class="p-5 text-sm text-slate-400 text-center">Todavía no se canjeó ninguna.</div>
                    </template>
                    <ul class="divide-y divide-slate-100">
                        <template x-for="g in recentRedeemed" :key="g.id">
                            <li>
                                <a :href="`/giftcards/${g.id}`" class="flex items-center gap-3 p-3 hover:bg-slate-50 transition">
                                    <template x-if="g.image_path">
                                        <img :src="`/uploads/${g.image_path}`" loading="lazy" class="w-10 h-10 rounded-lg object-cover border border-slate-200">
                                    </template>
                                    <template x-if="!g.image_path">
                                        <div class="w-10 h-10 rounded-lg bg-slate-100 flex items-center justify-center text-xs text-slate-400">—</div>
                                    </template>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-sm font-medium text-slate-800 truncate" x-text="g.title"></p>
                                        <p class="text-xs text-slate-500" x-text="`Canjeada por ${g.redeemed_by_name || '—'}`"></p>
                                    </div>
                                    <span class="text-xs text-slate-400" x-text="formatDate(g.redeemed_at)"></span>
                                </a>
                            </li>
                        </template>
                    </ul>
                </div>
            </div>

            <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <a href="/giftcards/new" class="block bg-white rounded-xl shadow-sm border border-slate-200 p-4 hover:border-slate-400 hover:shadow transition text-center">
                    <p class="font-semibold text-slate-800">+ Nueva giftcard</p>
                    <p class="text-xs text-slate-500 mt-1">Crear una nueva con QR.</p>
                </a>
                <a href="/scan" class="block bg-white rounded-xl shadow-sm border border-slate-200 p-4 hover:border-slate-400 hover:shadow transition text-center">
                    <p class="font-semibold text-slate-800">Escanear QR</p>
                    <p class="text-xs text-slate-500 mt-1">Marcar una como canjeada.</p>
                </a>
                <a href="/users" class="block bg-white rounded-xl shadow-sm border border-slate-200 p-4 hover:border-slate-400 hover:shadow transition text-center">
                    <p class="font-semibold text-slate-800">Usuarios</p>
                    <p class="text-xs text-slate-500 mt-1">Gestionar quién puede canjear.</p>
                </a>
                <a href="/guia" target="_blank" class="block bg-white rounded-xl shadow-sm border border-slate-200 p-4 hover:border-slate-400 hover:shadow transition text-center">
                    <p class="font-semibold text-slate-800">📘 Guía de uso</p>
                    <p class="text-xs text-slate-500 mt-1">Infografía imprimible del flujo.</p>
                </a>
            </div>
        </div>
    </template>

    <div x-show="error" x-cloak class="p-3 bg-red-50 border border-red-200 text-red-700 rounded-lg text-sm" x-text="error"></div>
</div>

<script>
    function dashboardPage() {
        return {
            stats: null,
            recentCreated: [],
            recentRedeemed: [],
            loading: true,
            error: '',
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
                return d.toLocaleDateString('es-AR', { day: '2-digit', month: 'short' });
            },
            async load() {
                this.loading = true;
                this.error = '';
                try {
                    const res = await window.api('/api/dashboard/stats');
                    if (!res) return;
                    const data = await res.json();
                    if (!res.ok) throw new Error(data.error || 'Error al cargar el dashboard.');
                    this.stats = data.stats;
                    this.recentCreated  = data.recent_created  || [];
                    this.recentRedeemed = data.recent_redeemed || [];
                } catch (e) {
                    this.error = e.message || 'Error de red.';
                } finally {
                    this.loading = false;
                }
            }
        }
    }
</script>
