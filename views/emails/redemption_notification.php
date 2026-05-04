<?php
/** @var array $giftcard */
/** @var string $appUrl */
use App\Helpers\View;

$senderFirstName = trim(explode(' ', (string) $giftcard['sender_name'])[0] ?? '');
$greeting        = $senderFirstName !== '' ? 'Hola ' . $senderFirstName : 'Hola';
$recipient       = $giftcard['recipient_name'] ?? null;
$establishment   = $giftcard['establishment_name'] ?? '';
$redeemedAt      = $giftcard['redeemed_at'] ?? '';
$redeemerName    = $giftcard['redeemed_by_name'] ?? '';
$brandColor      = $giftcard['establishment_primary_color'] ?? '#111827';
?>
<!DOCTYPE html>
<html lang="es">
<body style="margin:0; padding:0; background-color:#f1f5f9; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Helvetica, Arial, sans-serif; color:#0f172a;">
    <table role="presentation" align="center" width="600" cellpadding="0" cellspacing="0" style="max-width:600px; margin:24px auto; background:#ffffff; border-radius:12px; overflow:hidden; box-shadow:0 2px 8px rgba(15,23,42,0.06);">
        <tr>
            <td style="background-color:<?= View::escape($brandColor) ?>; padding:24px; color:#ffffff; text-align:center;">
                <h1 style="margin:0; font-size:20px; font-weight:700;">✓ Tu giftcard fue canjeada</h1>
            </td>
        </tr>
        <tr>
            <td style="padding:28px 24px;">
                <p style="font-size:16px; margin:0 0 16px;"><?= View::escape($greeting) ?>,</p>

                <p style="font-size:15px; line-height:1.5; margin:0 0 16px;">
                    Te avisamos que la giftcard
                    <strong>"<?= View::escape($giftcard['title'] ?? '') ?>"</strong>
                    <?php if ($recipient !== null && $recipient !== ''): ?>
                        que regalaste a <strong><?= View::escape($recipient) ?></strong>
                    <?php else: ?>
                        que regalaste
                    <?php endif; ?>
                    fue canjeada<?= $establishment !== '' ? ' en <strong>' . View::escape($establishment) . '</strong>' : '' ?>.
                </p>

                <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="margin:20px 0; background-color:#f8fafc; border:1px solid #e2e8f0; border-radius:8px;">
                    <tr>
                        <td style="padding:16px 18px; font-size:14px;">
                            <p style="margin:0 0 8px; color:#64748b; font-size:12px; text-transform:uppercase; letter-spacing:0.05em; font-weight:600;">Detalles del canje</p>
                            <p style="margin:0 0 6px;"><strong>Cuándo:</strong> <?= View::escape($redeemedAt) ?></p>
                            <?php if ($redeemerName !== ''): ?>
                                <p style="margin:0 0 6px;"><strong>Atendido por:</strong> <?= View::escape($redeemerName) ?></p>
                            <?php endif; ?>
                            <?php if (!empty($giftcard['expires_at'])): ?>
                                <p style="margin:0;"><strong>Vencía el:</strong> <?= View::escape($giftcard['expires_at']) ?></p>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>

                <p style="font-size:14px; line-height:1.5; color:#475569; margin:0 0 24px;">
                    Este email es solo informativo. La giftcard ya no se puede volver a canjear.
                </p>

                <p style="font-size:13px; color:#94a3b8; margin:24px 0 0; padding-top:16px; border-top:1px solid #e2e8f0;">
                    <?php if ($establishment !== ''): ?>
                        — <?= View::escape($establishment) ?>
                    <?php endif; ?>
                </p>
            </td>
        </tr>
    </table>
</body>
</html>
