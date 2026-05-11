<?php
/** @var array $giftcard */
/** @var array|null $establishment */
/** @var string $redeemUrl */
/** @var string $tokenShort */
/** @var string $qrCid */
use App\Helpers\View;

$recipientName = trim((string) ($giftcard['recipient_name'] ?? ''));
$greeting      = $recipientName !== '' ? 'Hola ' . $recipientName : 'Hola';
$senderName    = trim((string) ($giftcard['sender_name'] ?? ''));
$establishmentName  = (string) ($establishment['name'] ?? '');
$brandColor    = (string) ($establishment['primary_color'] ?? '#111827');
$senderLabel   = $senderName !== '' ? $senderName : ($establishmentName !== '' ? $establishmentName : 'Giftcards');
?>
<!DOCTYPE html>
<html lang="es">
<body style="margin:0; padding:0; background-color:#f1f5f9; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Helvetica, Arial, sans-serif; color:#0f172a;">
    <table role="presentation" align="center" width="600" cellpadding="0" cellspacing="0" style="max-width:600px; margin:24px auto; background:#ffffff; border-radius:12px; overflow:hidden; box-shadow:0 2px 8px rgba(15,23,42,0.06);">
        <tr>
            <td style="background-color:<?= View::escape($brandColor) ?>; padding:24px; color:#ffffff; text-align:center;">
                <h1 style="margin:0; font-size:20px; font-weight:700;">🎁 Tenés una giftcard</h1>
                <?php if ($establishmentName !== ''): ?>
                    <p style="margin:6px 0 0; font-size:13px; opacity:0.85;"><?= View::escape($establishmentName) ?></p>
                <?php endif; ?>
            </td>
        </tr>
        <tr>
            <td style="padding:28px 24px;">
                <p style="font-size:16px; margin:0 0 16px;"><?= View::escape($greeting) ?>,</p>

                <p style="font-size:15px; line-height:1.5; margin:0 0 16px;">
                    <strong><?= View::escape($senderLabel) ?></strong> te regaló una giftcard:
                </p>

                <p style="font-size:18px; font-weight:700; margin:0 0 8px;">
                    <?= View::escape((string) ($giftcard['title'] ?? '')) ?>
                </p>
                <?php if (!empty($giftcard['description'])): ?>
                    <p style="font-size:14px; color:#475569; line-height:1.5; margin:0 0 20px; white-space:pre-line;">
                        <?= View::escape((string) $giftcard['description']) ?>
                    </p>
                <?php endif; ?>

                <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="margin:20px 0; background-color:#f8fafc; border:1px solid #e2e8f0; border-radius:8px;">
                    <tr>
                        <td align="center" style="padding:24px;">
                            <p style="margin:0 0 12px; color:#64748b; font-size:12px; text-transform:uppercase; letter-spacing:0.05em; font-weight:600;">Tu código QR</p>
                            <img src="cid:<?= View::escape($qrCid) ?>" alt="QR de la giftcard" width="220" height="220" style="display:block; margin:0 auto; border:1px solid #e2e8f0; border-radius:8px; background:#ffffff;">
                            <p style="margin:14px 0 0; font-family: 'Courier New', monospace; font-size:18px; font-weight:700; letter-spacing:0.1em; color:#0f172a;">
                                <?= View::escape($tokenShort) ?>
                            </p>
                            <p style="margin:6px 0 0; font-size:12px; color:#94a3b8;">Código corto (por si no se ve el QR)</p>
                        </td>
                    </tr>
                </table>

                <p style="font-size:14px; line-height:1.6; margin:0 0 16px;">
                    Para canjearla, mostrá este QR (impreso o en tu celular)
                    <?= $establishmentName !== '' ? 'en <strong>' . View::escape($establishmentName) . '</strong>' : 'en el local' ?>.
                </p>

                <table role="presentation" cellpadding="0" cellspacing="0" align="center" style="margin:20px auto;">
                    <tr>
                        <td style="border-radius:8px; background-color:<?= View::escape($brandColor) ?>;">
                            <a href="<?= View::escape($redeemUrl) ?>"
                               style="display:inline-block; padding:12px 24px; font-size:14px; font-weight:600; color:#ffffff; text-decoration:none; border-radius:8px;">
                                Ver giftcard online
                            </a>
                        </td>
                    </tr>
                </table>

                <?php if (!empty($giftcard['expires_at'])): ?>
                    <p style="font-size:13px; color:#b45309; background:#fffbeb; border:1px solid #fde68a; border-radius:6px; padding:10px 12px; margin:16px 0 0;">
                        ⏰ Vence el <strong><?= View::escape((string) $giftcard['expires_at']) ?></strong>
                    </p>
                <?php endif; ?>

                <p style="font-size:13px; color:#94a3b8; margin:28px 0 0; padding-top:16px; border-top:1px solid #e2e8f0;">
                    Si tu cliente de email no muestra el QR, te lo dejamos como adjunto en este mismo correo. También podés abrir el link de arriba.
                </p>
            </td>
        </tr>
    </table>
</body>
</html>
