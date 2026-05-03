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
        <a href="/users" class="block bg-white rounded-xl shadow-sm border border-slate-200 p-5 hover:border-slate-400 hover:shadow-md transition">
            <div class="flex items-start gap-4">
                <div class="w-10 h-10 rounded-lg bg-slate-100 text-slate-700 flex items-center justify-center font-semibold">U</div>
                <div>
                    <h2 class="font-semibold text-slate-800">Usuarios</h2>
                    <p class="text-sm text-slate-500 mt-1">Dar de alta o desactivar a las personas que escanean QRs.</p>
                </div>
            </div>
        </a>

        <div class="block bg-white rounded-xl shadow-sm border border-slate-200 p-5 opacity-60 cursor-not-allowed">
            <div class="flex items-start gap-4">
                <div class="w-10 h-10 rounded-lg bg-slate-100 text-slate-400 flex items-center justify-center font-semibold">G</div>
                <div>
                    <h2 class="font-semibold text-slate-700">Giftcards</h2>
                    <p class="text-sm text-slate-500 mt-1">Próximamente — crear y administrar tus giftcards (Fase 4).</p>
                </div>
            </div>
        </div>
    </div>

    <div class="bg-slate-50 border border-slate-200 rounded-xl p-5 text-sm text-slate-600">
        <p class="font-medium text-slate-800 mb-1">Cómo funciona</p>
        <p>
            Por ahora podés gestionar los usuarios del establecimiento. En la próxima fase vas a poder crear giftcards
            con QR para entregar a tus clientes. Los usuarios que des de alta acá van a poder escanearlos y marcarlos
            como canjeados.
        </p>
    </div>
</div>
