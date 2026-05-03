<?php
/** @var \App\Auth\AuthenticatedUser $user */
?>
<div x-data="scanPage()" x-init="start()" class="max-w-xl mx-auto space-y-6">
    <div class="text-center">
        <h1 class="text-2xl font-bold text-slate-800">Escanear giftcard</h1>
        <p class="text-sm text-slate-500 mt-1">Apuntá la cámara al código QR del cliente.</p>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
        <div id="qr-reader" class="w-full bg-black"></div>
        <div class="p-4 text-center text-xs text-slate-500" x-show="cameraStatus" x-text="cameraStatus"></div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">
        <p class="text-sm font-medium text-slate-700 mb-3">¿No escanea? Tipeá el código corto del PDF / tarjeta:</p>
        <form @submit.prevent="lookupShort" class="flex gap-2">
            <input type="text" x-model="shortCode" maxlength="32" autocapitalize="characters"
                   class="flex-1 px-3 py-2 border border-slate-300 rounded-lg font-mono text-sm uppercase focus:ring-2 focus:ring-slate-800 focus:border-transparent outline-none transition"
                   placeholder="Ej: A1B2C3D4">
            <button type="submit" :disabled="lookupLoading || shortCode.length < 4"
                    class="bg-slate-800 hover:bg-slate-900 text-white text-sm font-semibold px-4 py-2 rounded-lg transition disabled:opacity-50 disabled:cursor-not-allowed">
                <span x-show="!lookupLoading">Buscar</span>
                <span x-show="lookupLoading" x-cloak>…</span>
            </button>
        </form>
        <p class="text-xs text-slate-500 mt-2">Mínimo 4 caracteres. Si hay coincidencia única te lleva al canje.</p>
    </div>

    <div x-show="error" x-cloak class="p-3 bg-red-50 border border-red-200 text-red-700 rounded-lg text-sm" x-text="error"></div>
</div>

<script src="https://unpkg.com/html5-qrcode@2.3.10/html5-qrcode.min.js"></script>
<script>
    function scanPage() {
        return {
            scanner: null,
            cameraStatus: 'Iniciando cámara…',
            shortCode: '',
            lookupLoading: false,
            error: '',
            tokenRegex: /\/redeem\/([a-f0-9]{32})/i,

            async start() {
                if (typeof Html5Qrcode === 'undefined') {
                    this.cameraStatus = 'No se pudo cargar el lector de QR. Probá con el código manual.';
                    return;
                }
                try {
                    this.scanner = new Html5Qrcode('qr-reader');
                    await this.scanner.start(
                        { facingMode: 'environment' },
                        { fps: 10, qrbox: { width: 250, height: 250 } },
                        (decoded) => this.onScan(decoded),
                        () => { /* parsing errors per frame; ignorar */ }
                    );
                    this.cameraStatus = 'Cámara activa — apuntá al QR';
                } catch (e) {
                    this.cameraStatus = 'No se pudo abrir la cámara (permiso denegado o navegador no soportado). Usá el código manual.';
                }
            },

            async onScan(decoded) {
                // Evitar lecturas duplicadas / múltiples
                if (this._handled) return;
                this._handled = true;
                try { await this.scanner?.stop(); } catch (e) {}

                // Aceptar solo si es una URL de /redeem/{token32}
                const m = decoded.match(this.tokenRegex);
                if (!m) {
                    this.error = 'El QR escaneado no es de una giftcard de este sistema.';
                    this._handled = false;
                    setTimeout(() => this.start(), 1000);
                    return;
                }
                window.location.href = '/redeem/' + m[1];
            },

            async lookupShort() {
                this.error = '';
                this.lookupLoading = true;
                try {
                    const res = await window.api('/api/redeem/lookup?short=' + encodeURIComponent(this.shortCode.trim().toLowerCase()));
                    if (!res) return;
                    const data = await res.json();
                    if (!res.ok) {
                        this.error = data.error || 'No se encontró el código.';
                        return;
                    }
                    window.location.href = '/redeem/' + data.token;
                } catch (e) {
                    this.error = e.message || 'Error de red.';
                } finally {
                    this.lookupLoading = false;
                }
            }
        }
    }
</script>
