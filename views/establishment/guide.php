<?php
/** @var array $establishment */
use App\Helpers\View;

$brandColor = $establishment['primary_color'] ?? '#111827';
$estName    = $establishment['name'] ?? 'Tu establecimiento';
?><!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Guía de uso — <?= View::escape($estName) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        :root { --brand: <?= View::escape($brandColor) ?>; }

        @page {
            size: A4 portrait;
            margin: 12mm;
        }

        body {
            font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", sans-serif;
            color: #0f172a;
            background: #f8fafc;
        }

        .toolbar { background: white; border-bottom: 1px solid #e2e8f0; padding: 0.75rem 1rem; }

        .page {
            max-width: 186mm;          /* A4 width 210mm - 12mm*2 márgenes */
            margin: 1.5rem auto;
            background: white;
            padding: 18mm 16mm;
            box-shadow: 0 4px 16px rgba(15,23,42,0.08);
            border-radius: 8px;
        }

        h1.title { color: var(--brand); font-size: 28pt; font-weight: 800; line-height: 1.05; }
        h2.section-title {
            color: white; background: var(--brand);
            display: inline-block; padding: 4px 14px; border-radius: 999px;
            font-size: 11pt; font-weight: 700; letter-spacing: 0.04em;
            text-transform: uppercase;
        }
        .step-num {
            background: var(--brand); color: white;
            width: 28px; height: 28px; border-radius: 50%;
            display: inline-flex; align-items: center; justify-content: center;
            font-weight: 800; font-size: 13pt; flex-shrink: 0;
        }
        .role-card { border: 2px solid #e2e8f0; border-radius: 12px; padding: 14px 16px; }
        .role-card.role-admin    { border-color: var(--brand); }
        .badge {
            display: inline-block; padding: 2px 10px; border-radius: 999px;
            font-size: 9pt; font-weight: 600;
        }

        @media print {
            body { background: white; }
            .toolbar, .no-print { display: none !important; }
            .page { margin: 0; box-shadow: none; border-radius: 0; padding: 0; max-width: none; }
            .section { page-break-inside: avoid; }
            h2.section-title { page-break-after: avoid; }
        }
    </style>
</head>
<body>
    <div class="toolbar no-print flex justify-between items-center max-w-4xl mx-auto sticky top-0 z-10">
        <a href="/dashboard" class="text-sm text-slate-600 hover:text-slate-900">← Volver al dashboard</a>
        <button onclick="window.print()" type="button"
                class="bg-slate-800 hover:bg-slate-900 text-white text-sm font-semibold px-4 py-2 rounded-lg">
            Imprimir / Guardar como PDF
        </button>
    </div>

    <div class="page text-[10pt] leading-relaxed">

        <!-- HEADER -->
        <header class="border-b-4 pb-4 mb-6" style="border-color: var(--brand);">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p class="text-xs uppercase tracking-widest text-slate-500 font-semibold mb-1">Guía operativa</p>
                    <h1 class="title">Cómo funciona tu sistema de giftcards</h1>
                </div>
                <?php if (!empty($establishment['logo_path'])): ?>
                    <img src="/uploads/<?= View::escape($establishment['logo_path']) ?>"
                         alt="" class="w-16 h-16 rounded-lg object-cover border border-slate-200">
                <?php endif; ?>
            </div>
            <p class="mt-3 text-slate-600">
                <strong><?= View::escape($estName) ?></strong> · Esta guía explica el ciclo completo:
                desde que creás una giftcard hasta que el cliente la canjea.
            </p>
        </header>

        <!-- 1. ¿QUÉ ES? -->
        <section class="section mb-6">
            <h2 class="section-title">1 · El sistema en 30 segundos</h2>
            <p class="mt-3 text-slate-700">
                Una <strong>giftcard digital</strong> es un beneficio (combo, descuento, producto, gift)
                que tu establecimiento crea, le entrega a un cliente como regalo o promoción, y que
                el cliente canjea presentando un <strong>código QR único</strong>. El sistema lleva
                el control automático de cuáles están vigentes, canjeadas, vencidas o canceladas.
            </p>
        </section>

        <!-- 2. ROLES -->
        <section class="section mb-6">
            <h2 class="section-title">2 · Los dos roles de tu equipo</h2>
            <div class="grid grid-cols-2 gap-4 mt-3">
                <div class="role-card role-admin">
                    <p class="font-bold text-base" style="color: var(--brand);">Admin del establecimiento</p>
                    <p class="text-xs text-slate-500 mt-0.5">Vos o quien gestione las giftcards.</p>
                    <ul class="mt-2 space-y-1 text-slate-700 list-disc list-inside text-[9pt]">
                        <li>Crea, edita y cancela giftcards</li>
                        <li>Da de alta operadores</li>
                        <li>Ve el dashboard con KPIs</li>
                        <li>Descarga QRs e imprime tarjetas</li>
                        <li><strong>También puede canjear</strong></li>
                    </ul>
                </div>
                <div class="role-card">
                    <p class="font-bold text-base text-slate-700">Operador</p>
                    <p class="text-xs text-slate-500 mt-0.5">El personal que atiende al cliente.</p>
                    <ul class="mt-2 space-y-1 text-slate-700 list-disc list-inside text-[9pt]">
                        <li><strong>Solo escanea y canjea</strong></li>
                        <li>Ve giftcards en modo lectura</li>
                        <li>No puede crear/editar/cancelar</li>
                        <li>No accede a usuarios ni dashboard</li>
                    </ul>
                </div>
            </div>
        </section>

        <!-- 3. CREAR GIFTCARD -->
        <section class="section mb-6">
            <h2 class="section-title">3 · Cómo creás una giftcard (admin)</h2>
            <ol class="mt-3 space-y-3">
                <li class="flex gap-3">
                    <span class="step-num">1</span>
                    <div><strong>Login</strong> en <code class="bg-slate-100 px-1 rounded text-[9pt]">gift.fidelun.com/login</code> → vas al Dashboard.</div>
                </li>
                <li class="flex gap-3">
                    <span class="step-num">2</span>
                    <div>Click en <strong>Giftcards → "+ Nueva giftcard"</strong>.</div>
                </li>
                <li class="flex gap-3">
                    <span class="step-num">3</span>
                    <div>Completá:
                        <ul class="list-disc list-inside text-slate-600 mt-1 ml-2 text-[9pt]">
                            <li><strong>Título</strong> (ej. "Combo para 2"), <strong>descripción</strong>, <strong>imagen</strong> (JPG/PNG/WebP, máx 2 MB)</li>
                            <li><strong>Destinatario</strong> y <strong>vencimiento</strong> (opcionales)</li>
                            <li><strong>Quien regala</strong> + email — si cargás el email, le avisamos automáticamente cuando se canjee</li>
                        </ul>
                    </div>
                </li>
                <li class="flex gap-3">
                    <span class="step-num">4</span>
                    <div>Click <strong>"Crear giftcard"</strong> → el sistema genera un <strong>código QR único</strong> y un código corto (8 caracteres en mayúsculas).</div>
                </li>
                <li class="flex gap-3">
                    <span class="step-num">5</span>
                    <div>Vas directo al detalle: ahí están el QR, los datos y los botones de descarga / compartir.</div>
                </li>
            </ol>
        </section>

        <!-- 4. ENTREGAR -->
        <section class="section mb-6">
            <h2 class="section-title">4 · Cómo se la hacés llegar al cliente</h2>
            <div class="grid grid-cols-3 gap-3 mt-3">
                <div class="border border-slate-200 rounded-lg p-3">
                    <p class="font-bold text-slate-800 text-[10pt]">📲 WhatsApp</p>
                    <p class="text-slate-600 text-[9pt] mt-1">
                        Botón "Compartir por WhatsApp" en el detalle. Abre con un mensaje pre-armado y el link al QR.
                    </p>
                </div>
                <div class="border border-slate-200 rounded-lg p-3">
                    <p class="font-bold text-slate-800 text-[10pt]">🖨 Tarjeta impresa</p>
                    <p class="text-slate-600 text-[9pt] mt-1">
                        Botón "Imprimir tarjeta" → genera una tarjeta A6 lista para imprimir, con tu logo y color.
                        Para entregar en mano.
                    </p>
                </div>
                <div class="border border-slate-200 rounded-lg p-3">
                    <p class="font-bold text-slate-800 text-[10pt]">🔗 Link directo</p>
                    <p class="text-slate-600 text-[9pt] mt-1">
                        Descargá el QR como PNG o copiá el link <code class="text-[8pt]">/redeem/{token}</code>
                        y mandalo por cualquier medio.
                    </p>
                </div>
            </div>
        </section>

        <!-- 5. CANJEAR -->
        <section class="section mb-6">
            <h2 class="section-title">5 · Cómo se canjea cuando llega el cliente</h2>
            <ol class="mt-3 space-y-3">
                <li class="flex gap-3">
                    <span class="step-num">1</span>
                    <div>El cliente llega al local con el QR (impreso, en el celular, por WhatsApp).</div>
                </li>
                <li class="flex gap-3">
                    <span class="step-num">2</span>
                    <div>El operador abre <strong>Escanear</strong> en el menú (o entra a <code class="bg-slate-100 px-1 rounded text-[9pt]">/scan</code>).</div>
                </li>
                <li class="flex gap-3">
                    <span class="step-num">3</span>
                    <div>El celular pide permiso de cámara → activa el lector → apunta al QR del cliente.
                        <br><span class="text-slate-500 text-[9pt]">Si el QR no escanea bien: hay un input para tipear el <strong>código corto</strong> (8 caracteres) que aparece en la tarjeta.</span>
                    </div>
                </li>
                <li class="flex gap-3">
                    <span class="step-num">4</span>
                    <div>Aparece la pantalla de canje con la imagen, los datos de la giftcard y un botón verde grande <strong>"✓ Marcar como canjeada"</strong>.</div>
                </li>
                <li class="flex gap-3">
                    <span class="step-num">5</span>
                    <div>El operador toca el botón → confirma → la giftcard pasa a estado <strong>Canjeada</strong>. Si tenía email del regalador, le llega un mail automático en segundos.</div>
                </li>
            </ol>
            <div class="mt-3 p-3 bg-amber-50 border border-amber-200 rounded-lg text-[9pt] text-amber-900">
                <strong>Importante:</strong> el operador <strong>tiene que estar logueado</strong> con un usuario de TU establecimiento.
                Si alguien escanea el QR de un cliente sin login, ve la giftcard pero NO puede canjearla — lo lleva a la pantalla de login primero.
            </div>
        </section>

        <!-- 6. ESTADOS -->
        <section class="section mb-6">
            <h2 class="section-title">6 · Los 4 estados de una giftcard</h2>
            <div class="grid grid-cols-4 gap-3 mt-3">
                <div class="border border-slate-200 rounded-lg p-3 text-center">
                    <span class="badge bg-emerald-100 text-emerald-700">Vigente</span>
                    <p class="text-[9pt] text-slate-600 mt-2">Lista para canjear. Vence en la fecha cargada (si tiene vencimiento).</p>
                </div>
                <div class="border border-slate-200 rounded-lg p-3 text-center">
                    <span class="badge bg-indigo-100 text-indigo-700">Canjeada</span>
                    <p class="text-[9pt] text-slate-600 mt-2">Ya fue usada. No puede volver a canjearse.</p>
                </div>
                <div class="border border-slate-200 rounded-lg p-3 text-center">
                    <span class="badge bg-amber-100 text-amber-700">Vencida</span>
                    <p class="text-[9pt] text-slate-600 mt-2">Pasó la fecha de vencimiento sin canjearse.</p>
                </div>
                <div class="border border-slate-200 rounded-lg p-3 text-center">
                    <span class="badge bg-slate-200 text-slate-600">Cancelada</span>
                    <p class="text-[9pt] text-slate-600 mt-2">El admin la canceló manualmente.</p>
                </div>
            </div>
        </section>

        <!-- 7. NOTIFICACIÓN -->
        <section class="section mb-6">
            <h2 class="section-title">7 · Notificación automática al regalador</h2>
            <p class="mt-3 text-slate-700">
                Si al crear la giftcard cargás el campo <strong>"Email de quien regala"</strong>, el sistema envía
                automáticamente un email a esa dirección cuando la giftcard se canjea, con:
            </p>
            <ul class="list-disc list-inside text-slate-600 mt-2 ml-2 text-[9pt]">
                <li>Título de la giftcard y a quién se la regaló</li>
                <li>Cuándo y dónde se canjeó</li>
                <li>El nombre del operador que la atendió</li>
            </ul>
            <p class="mt-2 text-[9pt] text-slate-500">
                Es una buena forma de cerrar el círculo: la persona que regaló se entera de que su gesto llegó al destinatario.
            </p>
        </section>

        <!-- 8. TIPS -->
        <section class="section">
            <h2 class="section-title">8 · Tips útiles</h2>
            <ul class="mt-3 space-y-2 text-slate-700 text-[10pt]">
                <li class="flex gap-2"><span style="color: var(--brand);">▸</span>
                    <span><strong>Dashboard</strong> tiene KPIs en vivo — total emitidas, canjeadas este mes, % de canje histórico.</span>
                </li>
                <li class="flex gap-2"><span style="color: var(--brand);">▸</span>
                    <span>Las pestañas del listado <strong>"Vigentes / Canjeadas / Vencidas / Canceladas"</strong> filtran al instante.</span>
                </li>
                <li class="flex gap-2"><span style="color: var(--brand);">▸</span>
                    <span>El operador puede entrar al sistema desde el celular — la interfaz se adapta a pantalla chica.</span>
                </li>
                <li class="flex gap-2"><span style="color: var(--brand);">▸</span>
                    <span>Si una giftcard ya fue canjeada y el cliente vuelve con el mismo QR, el sistema muestra cuándo y quién la canjeó. <strong>Imposible canjearla dos veces.</strong></span>
                </li>
                <li class="flex gap-2"><span style="color: var(--brand);">▸</span>
                    <span>Cambiar la contraseña: <strong>Avatar arriba a la derecha → Mi perfil</strong>.</span>
                </li>
            </ul>
        </section>

        <footer class="mt-8 pt-4 border-t border-slate-200 text-[8pt] text-slate-400 text-center">
            Sistema de giftcards · <?= View::escape($estName) ?>
            · Generado el <?= date('d/m/Y') ?>
        </footer>

    </div>
</body>
</html>
