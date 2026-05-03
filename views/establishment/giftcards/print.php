<?php
/** @var array $giftcard */
/** @var array $establishment */
/** @var string $qrDataUri */
/** @var string $tokenShort */
/** @var string $redeemUrl */
use App\Helpers\View;

$primaryColor = $establishment['primary_color'] ?? '#111827';
?><!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Imprimir — <?= View::escape($giftcard['title']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        :root { --brand-color: <?= View::escape($primaryColor) ?>; }

        @page {
            size: A6 portrait;
            margin: 8mm;
        }

        body {
            font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", sans-serif;
            color: #0f172a;
            background: #f1f5f9;
        }

        .toolbar { background: white; border-bottom: 1px solid #e2e8f0; padding: 0.75rem 1rem; }
        .toolbar button {
            background: #1e293b; color: white; padding: 0.5rem 1rem; border-radius: 0.5rem;
            font-weight: 600; font-size: 0.875rem;
        }

        .card-frame {
            margin: 1.5rem auto; width: 105mm; min-height: 148mm;
            background: white; border: 1px solid #e2e8f0; border-radius: 6px;
            box-shadow: 0 4px 12px rgba(15, 23, 42, 0.08);
            display: flex; flex-direction: column; overflow: hidden;
            page-break-inside: avoid;
        }

        .card-header {
            background: var(--brand-color); color: white;
            padding: 6mm 5mm; display: flex; align-items: center; gap: 4mm;
        }
        .card-header img { width: 12mm; height: 12mm; border-radius: 4px; object-fit: cover; background: white; }
        .card-header h2 { font-size: 11pt; font-weight: 700; letter-spacing: 0.02em; line-height: 1.1; }
        .card-header p  { font-size: 7pt; opacity: 0.8; line-height: 1.1; margin-top: 1mm; }

        .card-body { padding: 4mm 5mm; flex: 1; display: flex; flex-direction: column; gap: 3mm; }
        .card-body img.gift-img {
            width: 100%; max-height: 38mm; object-fit: cover; border-radius: 4px; border: 1px solid #e2e8f0;
        }
        .card-body h1 { font-size: 14pt; font-weight: 800; color: #0f172a; line-height: 1.15; }
        .card-body p.desc { font-size: 9pt; color: #334155; line-height: 1.3; }
        .card-body p.recipient { font-size: 8pt; color: #64748b; }

        .qr-block {
            display: flex; flex-direction: column; align-items: center; gap: 2mm;
            border-top: 1px dashed #cbd5e1; padding-top: 3mm;
        }
        .qr-block img { width: 36mm; height: 36mm; }
        .qr-block .short-token {
            font-family: ui-monospace, SFMono-Regular, Consolas, "Liberation Mono", monospace;
            font-weight: 700; font-size: 11pt; letter-spacing: 0.15em;
            color: var(--brand-color); margin-top: 1mm;
        }

        .card-footer {
            background: #f8fafc; padding: 3mm 5mm;
            font-size: 7pt; color: #475569; line-height: 1.3;
            border-top: 1px solid #e2e8f0;
        }
        .card-footer p { margin: 0.5mm 0; }
        .card-footer strong { color: #0f172a; }

        @media print {
            body { background: white; }
            .toolbar { display: none; }
            .card-frame {
                margin: 0; width: 100%; min-height: auto;
                border: none; border-radius: 0; box-shadow: none;
                page-break-inside: avoid;
            }
        }
    </style>
</head>
<body>
    <div class="toolbar print:hidden flex justify-between items-center">
        <a href="/giftcards/<?= (int) $giftcard['id'] ?>" class="text-sm text-slate-600 hover:text-slate-900">← Volver al detalle</a>
        <div class="flex items-center gap-3">
            <span class="text-xs text-slate-500 hidden sm:inline">Tip: en el diálogo de impresión, elegí "Guardar como PDF" si querés un PDF.</span>
            <button onclick="window.print()" type="button">Imprimir</button>
        </div>
    </div>

    <div class="card-frame">
        <div class="card-header">
            <?php if (!empty($establishment['logo_path'])): ?>
                <img src="/uploads/<?= View::escape($establishment['logo_path']) ?>" alt="">
            <?php else: ?>
                <div class="text-2xl font-bold opacity-60"><?= View::escape(mb_substr($establishment['name'], 0, 1)) ?></div>
            <?php endif; ?>
            <div>
                <h2><?= View::escape($establishment['name']) ?></h2>
                <p>Giftcard digital</p>
            </div>
        </div>

        <div class="card-body">
            <?php if (!empty($giftcard['image_path'])): ?>
                <img class="gift-img" src="/uploads/<?= View::escape($giftcard['image_path']) ?>" alt="<?= View::escape($giftcard['title']) ?>">
            <?php endif; ?>
            <h1><?= View::escape($giftcard['title']) ?></h1>
            <?php if (!empty($giftcard['description'])): ?>
                <p class="desc"><?= nl2br(View::escape($giftcard['description'])) ?></p>
            <?php endif; ?>
            <?php if (!empty($giftcard['recipient_name'])): ?>
                <p class="recipient"><strong>Para:</strong> <?= View::escape($giftcard['recipient_name']) ?></p>
            <?php endif; ?>

            <div class="qr-block">
                <img src="<?= View::escape($qrDataUri) ?>" alt="QR de la giftcard">
                <div class="short-token"><?= View::escape($tokenShort) ?></div>
            </div>
        </div>

        <div class="card-footer">
            <p><strong>Para canjear:</strong> presentá este QR en <?= View::escape($establishment['name']) ?>.</p>
            <?php if (!empty($establishment['address']) || !empty($establishment['phone'])): ?>
                <p>
                    <?= !empty($establishment['address']) ? View::escape($establishment['address']) : '' ?>
                    <?= (!empty($establishment['address']) && !empty($establishment['phone'])) ? ' · ' : '' ?>
                    <?= !empty($establishment['phone']) ? View::escape($establishment['phone']) : '' ?>
                </p>
            <?php endif; ?>
            <?php if (!empty($giftcard['expires_at'])): ?>
                <p><strong>Vence:</strong> <?= View::escape($giftcard['expires_at']) ?></p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
