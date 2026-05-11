<?php
/** @var \App\Auth\AuthenticatedUser $user */
/** @var array $giftcard */
/** @var string $redeemUrl */
/** @var string $tokenShort */
/** @var string $qrDataUri */
use App\Helpers\View;

$canManage  = $user->isEstablishmentAdmin();
$status     = (string) $giftcard['status'];
$isActive   = $status === 'active';
$statusLabels = [
    'active'    => 'Vigente',
    'redeemed'  => 'Canjeada',
    'expired'   => 'Vencida',
    'cancelled' => 'Cancelada',
];
$statusClasses = [
    'active'    => 'bg-emerald-100 text-emerald-700',
    'redeemed'  => 'bg-indigo-100 text-indigo-700',
    'expired'   => 'bg-amber-100 text-amber-700',
    'cancelled' => 'bg-slate-200 text-slate-600',
];
$senderLabel  = !empty($giftcard['sender_name']) ? (string) $giftcard['sender_name'] : (string) $user->name;
$whatsappText = "Hola! Te paso esta giftcard de " . $senderLabel . ". Canjeala en este link: " . $redeemUrl;
$whatsappUrl  = 'https://wa.me/?text=' . rawurlencode($whatsappText);

$recipientContact = (string) ($giftcard['recipient_contact'] ?? '');
$emailTo          = (filter_var($recipientContact, FILTER_VALIDATE_EMAIL) !== false) ? $recipientContact : '';
$emailSubject     = 'Tenés una giftcard de ' . $senderLabel;
$emailBody        = "Hola" . (!empty($giftcard['recipient_name']) ? ' ' . $giftcard['recipient_name'] : '') . ",\n\n"
                  . "Te paso esta giftcard de " . $senderLabel . ".\n"
                  . "Canjeala en este link: " . $redeemUrl . "\n\n"
                  . "¡Disfrutala!";
$emailUrl         = 'mailto:' . rawurlencode($emailTo)
                  . '?subject=' . rawurlencode($emailSubject)
                  . '&body=' . rawurlencode($emailBody);
?>
<div x-data="{ cancelLoading: false, error: '' }" class="space-y-6">
    <div>
        <a href="/giftcards" class="text-sm text-slate-500 hover:text-slate-700">← Volver al listado</a>
        <div class="mt-2 flex items-start justify-between gap-4 flex-wrap">
            <h1 class="text-2xl font-bold text-slate-800"><?= View::escape($giftcard['title']) ?></h1>
            <span class="inline-block px-2.5 py-1 text-xs font-medium rounded-full <?= $statusClasses[$status] ?? 'bg-slate-100 text-slate-700' ?>">
                <?= View::escape($statusLabels[$status] ?? $status) ?>
            </span>
        </div>
    </div>

    <div class="grid lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 space-y-6">
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
                <?php if (!empty($giftcard['image_path'])): ?>
                    <img src="/uploads/<?= View::escape($giftcard['image_path']) ?>"
                         alt="<?= View::escape($giftcard['title']) ?>"
                         class="w-full max-h-80 object-cover">
                <?php else: ?>
                    <div class="w-full h-48 bg-slate-100 flex items-center justify-center text-slate-400 text-sm">
                        Sin imagen
                    </div>
                <?php endif; ?>
                <div class="p-5 space-y-3">
                    <?php if (!empty($giftcard['description'])): ?>
                        <p class="text-slate-700 whitespace-pre-line"><?= View::escape($giftcard['description']) ?></p>
                    <?php endif; ?>
                    <dl class="grid sm:grid-cols-2 gap-4 text-sm pt-2">
                        <?php if (!empty($giftcard['recipient_name'])): ?>
                            <div>
                                <dt class="text-slate-500">Destinatario</dt>
                                <dd class="font-medium text-slate-800"><?= View::escape($giftcard['recipient_name']) ?></dd>
                                <?php if (!empty($giftcard['recipient_contact'])): ?>
                                    <dd class="text-xs text-slate-500"><?= View::escape($giftcard['recipient_contact']) ?></dd>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($giftcard['sender_name']) || !empty($giftcard['sender_email'])): ?>
                            <div>
                                <dt class="text-slate-500">De</dt>
                                <dd class="font-medium text-slate-800"><?= View::escape($giftcard['sender_name'] ?? '—') ?></dd>
                                <?php if (!empty($giftcard['sender_email'])): ?>
                                    <dd class="text-xs text-slate-500"><?= View::escape($giftcard['sender_email']) ?></dd>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        <div>
                            <dt class="text-slate-500">Vencimiento</dt>
                            <dd class="font-medium text-slate-800"><?= $giftcard['expires_at'] ? View::escape($giftcard['expires_at']) : 'Sin vencimiento' ?></dd>
                        </div>
                        <div>
                            <dt class="text-slate-500">Creada</dt>
                            <dd class="font-medium text-slate-800"><?= View::escape($giftcard['created_at']) ?></dd>
                            <dd class="text-xs text-slate-500">por <?= View::escape($giftcard['created_by_name'] ?? '—') ?></dd>
                        </div>
                        <?php if (!empty($giftcard['redeemed_at'])): ?>
                            <div>
                                <dt class="text-slate-500">Canjeada</dt>
                                <dd class="font-medium text-slate-800"><?= View::escape($giftcard['redeemed_at']) ?></dd>
                                <dd class="text-xs text-slate-500">por <?= View::escape($giftcard['redeemed_by_name'] ?? '—') ?></dd>
                            </div>
                        <?php endif; ?>
                    </dl>
                </div>
            </div>
        </div>

        <div class="space-y-4">
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5 text-center">
                <p class="text-xs text-slate-500 uppercase tracking-wide font-semibold mb-3">Código QR</p>
                <img src="<?= View::escape($qrDataUri) ?>" alt="QR de la giftcard" class="mx-auto rounded-lg border border-slate-200">
                <p class="mt-3 text-sm text-slate-500">Código corto:</p>
                <p class="font-mono font-semibold text-slate-800 text-lg tracking-wider"><?= View::escape($tokenShort) ?></p>
                <p class="mt-2 text-xs text-slate-400 break-all"><?= View::escape($redeemUrl) ?></p>
            </div>

            <?php if ($canManage): ?>
                <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5 space-y-2">
                    <a href="/api/giftcards/<?= (int) $giftcard['id'] ?>/qr"
                       class="block w-full text-center bg-slate-800 hover:bg-slate-900 text-white text-sm font-semibold py-2.5 rounded-lg transition">
                        Descargar QR (PNG)
                    </a>
                    <a href="<?= View::escape($whatsappUrl) ?>" target="_blank" rel="noopener"
                       class="block w-full text-center bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold py-2.5 rounded-lg transition">
                        Compartir por WhatsApp
                    </a>
                    <a href="<?= View::escape($emailUrl) ?>"
                       class="block w-full text-center bg-sky-600 hover:bg-sky-700 text-white text-sm font-semibold py-2.5 rounded-lg transition">
                        Enviar por email
                    </a>
                    <a href="/giftcards/<?= (int) $giftcard['id'] ?>/print" target="_blank"
                       class="block w-full text-center bg-white hover:bg-slate-50 text-slate-700 text-sm font-semibold py-2.5 rounded-lg border border-slate-300 transition">
                        Imprimir tarjeta
                    </a>
                    <?php if ($isActive): ?>
                        <a href="/giftcards/<?= (int) $giftcard['id'] ?>/edit"
                           class="block w-full text-center bg-white hover:bg-slate-50 text-slate-700 text-sm font-semibold py-2.5 rounded-lg border border-slate-300 transition">
                            Editar
                        </a>
                        <button type="button" :disabled="cancelLoading"
                                @click="if (confirm('¿Cancelar esta giftcard? No se puede deshacer.')) {
                                    cancelLoading = true; error = '';
                                    window.api('/api/giftcards/<?= (int) $giftcard['id'] ?>', { method: 'DELETE' })
                                        .then(r => r ? r.json() : null).then(d => {
                                            if (d && d.ok) window.location.href = '/giftcards';
                                            else { error = (d && d.error) || 'Error al cancelar.'; cancelLoading = false; }
                                        }).catch(e => { error = e.message || 'Error de red.'; cancelLoading = false; });
                                }"
                                class="block w-full text-center bg-white hover:bg-red-50 text-red-700 text-sm font-semibold py-2.5 rounded-lg border border-red-300 transition disabled:opacity-50">
                            <span x-show="!cancelLoading">Cancelar giftcard</span>
                            <span x-show="cancelLoading" x-cloak>Cancelando…</span>
                        </button>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div x-show="error" x-cloak class="p-3 bg-red-50 border border-red-200 text-red-700 rounded-lg text-sm" x-text="error"></div>
</div>
