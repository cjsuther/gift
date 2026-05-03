<?php
/** @var \App\Auth\AuthenticatedUser $user */
use App\Helpers\View;
?>
<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-bold text-slate-800">Hola, <?= View::escape($user->name) ?>.</h1>
        <p class="text-sm text-slate-500 mt-1">Este es tu panel de gestión del establecimiento.</p>
    </div>

    <div class="grid sm:grid-cols-2 gap-4">
        <a href="/giftcards" class="block bg-white rounded-xl shadow-sm border border-slate-200 p-5 hover:border-slate-400 hover:shadow-md transition">
            <div class="flex items-start gap-4">
                <div class="w-10 h-10 rounded-lg bg-emerald-100 text-emerald-700 flex items-center justify-center font-semibold">G</div>
                <div>
                    <h2 class="font-semibold text-slate-800">Giftcards</h2>
                    <p class="text-sm text-slate-500 mt-1">Crear, editar y descargar las giftcards de tu establecimiento.</p>
                </div>
            </div>
        </a>

        <a href="/users" class="block bg-white rounded-xl shadow-sm border border-slate-200 p-5 hover:border-slate-400 hover:shadow-md transition">
            <div class="flex items-start gap-4">
                <div class="w-10 h-10 rounded-lg bg-slate-100 text-slate-700 flex items-center justify-center font-semibold">U</div>
                <div>
                    <h2 class="font-semibold text-slate-800">Usuarios</h2>
                    <p class="text-sm text-slate-500 mt-1">Dar de alta o desactivar a las personas que escanean QRs.</p>
                </div>
            </div>
        </a>
    </div>

    <div class="bg-slate-50 border border-slate-200 rounded-xl p-5 text-sm text-slate-600">
        <p class="font-medium text-slate-800 mb-1">Cómo funciona</p>
        <p>
            Creá giftcards con título, descripción opcional, imagen y vencimiento. Cada una genera un QR único que
            podés descargar en PNG y compartir por WhatsApp. Los usuarios que des de alta acá van a poder escanear
            esos QRs y marcarlos como canjeados (próxima fase).
        </p>
    </div>
</div>
