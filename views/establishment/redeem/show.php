<?php
/** @var \App\Auth\AuthenticatedUser $user */
/** @var array|null $giftcard */
/** @var string|null $crossTenantMessage  Mensaje si la giftcard pertenece a otro establecimiento */
/** @var string $token */
use App\Helpers\View;

// Estado visual derivado del status + vencimiento
$status     = $giftcard ? (string) $giftcard['status'] : 'not_found';
$today      = date('Y-m-d');
$isExpired  = $giftcard && !empty($giftcard['expires_at']) && $giftcard['expires_at'] < $today && $status === 'active';
if ($isExpired) {
    $status = 'expired_by_date';
}

$canRedeem = $giftcard && $status === 'active';

$badgeMap = [
    'active'           => ['Vigente',                     'bg-emerald-100 text-emerald-700'],
    'redeemed'         => ['Ya canjeada',                 'bg-indigo-100 text-indigo-700'],
    'expired'          => ['Vencida',                     'bg-amber-100 text-amber-700'],
    'expired_by_date'  => ['Vencida',                     'bg-amber-100 text-amber-700'],
    'cancelled'        => ['Cancelada',                   'bg-slate-200 text-slate-600'],
    'not_found'        => ['No encontrada',               'bg-red-100 text-red-700'],
    'cross_tenant'     => ['No pertenece a este local',   'bg-red-100 text-red-700'],
];
[$badgeLabel, $badgeClass] = $badgeMap[$status] ?? $badgeMap['not_found'];
$jsToken = json_encode($token, JSON_UNESCAPED_SLASHES);
?>
<div x-data='redeemPage(<?= $jsToken ?>)' class="max-w-xl mx-auto space-y-6">
    <div class="text-center">
        <span class="inline-block px-3 py-1 text-sm font-medium rounded-full <?= $badgeClass ?>">
            <?= View::escape($badgeLabel) ?>
        </span>
    </div>

    <?php if ($crossTenantMessage !== null): ?>
        <div class="bg-red-50 border border-red-200 text-red-700 rounded-xl p-5 text-center">
            <p class="font-semibold mb-1">No podés canjear esta giftcard.</p>
            <p class="text-sm"><?= View::escape($crossTenantMessage) ?></p>
        </div>
    <?php elseif ($giftcard === null): ?>
        <div class="bg-red-50 border border-red-200 text-red-700 rounded-xl p-5 text-center">
            <p class="font-semibold mb-1">Esta giftcard no existe en tu establecimiento.</p>
            <p class="text-sm">Verificá que el QR sea de un cliente tuyo, o que el token escaneado esté completo.</p>
        </div>
    <?php else: ?>
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
            <?php if (!empty($giftcard['image_path'])): ?>
                <img src="/uploads/<?= View::escape($giftcard['image_path']) ?>"
                     alt="<?= View::escape($giftcard['title']) ?>"
                     class="w-full max-h-64 object-cover">
            <?php endif; ?>
            <div class="p-5 space-y-3">
                <h1 class="text-2xl font-bold text-slate-800"><?= View::escape($giftcard['title']) ?></h1>
                <?php if (!empty($giftcard['description'])): ?>
                    <p class="text-slate-700 whitespace-pre-line"><?= View::escape($giftcard['description']) ?></p>
                <?php endif; ?>
                <dl class="grid grid-cols-2 gap-3 text-sm pt-2 border-t border-slate-100">
                    <?php if (!empty($giftcard['recipient_name'])): ?>
                        <div>
                            <dt class="text-slate-500">Para</dt>
                            <dd class="font-medium text-slate-800"><?= View::escape($giftcard['recipient_name']) ?></dd>
                        </div>
                    <?php endif; ?>
                    <div>
                        <dt class="text-slate-500">Vence</dt>
                        <dd class="font-medium text-slate-800"><?= $giftcard['expires_at'] ? View::escape($giftcard['expires_at']) : 'Sin vencimiento' ?></dd>
                    </div>
                    <?php if (!empty($giftcard['redeemed_at'])): ?>
                        <div class="col-span-2">
                            <dt class="text-slate-500">Canjeada</dt>
                            <dd class="font-medium text-slate-800"><?= View::escape($giftcard['redeemed_at']) ?> <span class="text-xs text-slate-500">por <?= View::escape($giftcard['redeemed_by_name'] ?? '—') ?></span></dd>
                        </div>
                    <?php endif; ?>
                </dl>
            </div>
        </div>

        <?php if ($canRedeem): ?>
            <div class="space-y-2">
                <button type="button" @click="confirmAndRedeem()" :disabled="loading"
                        class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-bold py-4 rounded-xl text-lg shadow transition disabled:opacity-50 disabled:cursor-not-allowed">
                    <span x-show="!loading">✓  Marcar como canjeada</span>
                    <span x-show="loading" x-cloak>Procesando…</span>
                </button>
                <a href="/scan" class="block w-full text-center text-sm text-slate-500 hover:text-slate-700 py-2">
                    Cancelar y volver a escanear
                </a>
            </div>
        <?php elseif ($status === 'redeemed'): ?>
            <div class="bg-indigo-50 border border-indigo-200 text-indigo-800 rounded-xl p-4 text-center text-sm">
                Esta giftcard ya fue canjeada. No se puede volver a usar.
            </div>
            <a href="/scan" class="block w-full text-center bg-slate-800 hover:bg-slate-900 text-white font-semibold py-3 rounded-xl">
                Escanear otra
            </a>
        <?php elseif ($status === 'expired_by_date' || $status === 'expired'): ?>
            <div class="bg-amber-50 border border-amber-200 text-amber-800 rounded-xl p-4 text-center text-sm">
                Esta giftcard venció el <?= View::escape($giftcard['expires_at'] ?? '—') ?> y ya no puede canjearse.
            </div>
            <a href="/scan" class="block w-full text-center bg-slate-800 hover:bg-slate-900 text-white font-semibold py-3 rounded-xl">
                Escanear otra
            </a>
        <?php elseif ($status === 'cancelled'): ?>
            <div class="bg-slate-50 border border-slate-200 text-slate-700 rounded-xl p-4 text-center text-sm">
                Esta giftcard fue cancelada por el establecimiento.
            </div>
            <a href="/scan" class="block w-full text-center bg-slate-800 hover:bg-slate-900 text-white font-semibold py-3 rounded-xl">
                Escanear otra
            </a>
        <?php endif; ?>
    <?php endif; ?>

    <div x-show="error" x-cloak class="p-3 bg-red-50 border border-red-200 text-red-700 rounded-lg text-sm" x-text="error"></div>
</div>

<script>
    function redeemPage(token) {
        return {
            token,
            loading: false,
            error: '',
            confirmAndRedeem() {
                if (!confirm('¿Confirmás el canje? Esta acción no se puede deshacer.')) return;
                this.redeem();
            },
            async redeem() {
                this.loading = true;
                this.error = '';
                try {
                    const res = await window.api('/api/redeem/' + this.token, { method: 'POST' });
                    if (!res) return;
                    const data = await res.json();
                    if (!res.ok) {
                        this.error = data.error || 'No se pudo canjear.';
                        // Si fue redimida o cambió de estado, refrescar para mostrar el nuevo estado
                        if (res.status === 422 || res.status === 409) {
                            setTimeout(() => window.location.reload(), 1500);
                        }
                        return;
                    }
                    // Éxito: feedback visual y redirección
                    alert('✓ Giftcard canjeada con éxito.');
                    window.location.href = '/scan';
                } catch (e) {
                    this.error = e.message || 'Error de red. Reintentá.';
                } finally {
                    this.loading = false;
                }
            }
        }
    }
</script>
