#!/usr/bin/env bash
#
# Deploy script para Hostinger.
#
# Uso (desde la raíz del proyecto en el server):
#   ./deploy.sh                  # full deploy: git pull + composer + opcache + health
#   ./deploy.sh --quick          # skip composer install (cuando no cambiaron deps)
#   ./deploy.sh --skip-composer  # alias de --quick
#
# Variables de entorno opcionales:
#   PHP=/opt/alt/php83/usr/bin/php   # binario de PHP a usar (default: 8.3)
#   COMPOSER=/usr/local/bin/composer # binario de composer
#   APP_URL=https://gift.fidelun.com # URL pública para los curl checks
#

set -euo pipefail

PHP="${PHP:-/opt/alt/php83/usr/bin/php}"
COMPOSER="${COMPOSER:-/usr/local/bin/composer}"
APP_URL="${APP_URL:-https://gift.fidelun.com}"

QUICK_MODE=0
for arg in "$@"; do
    case "$arg" in
        --quick|--skip-composer) QUICK_MODE=1 ;;
        -h|--help)
            sed -n '2,16p' "$0" | sed 's/^# //'
            exit 0
            ;;
    esac
done

# Verificar que estamos en la raíz del proyecto
if [ ! -f "composer.json" ] || [ ! -d "public_html" ]; then
    echo "✗ Este script debe correrse desde la raíz del proyecto (la carpeta que contiene composer.json y public_html/)."
    echo "  Probá: cd ~/domains/gift.fidelun.com/ && ./deploy.sh"
    exit 1
fi

# Verificar que el binario de PHP existe
if [ ! -x "$PHP" ]; then
    echo "✗ No encuentro el PHP en $PHP. Configuralo con: PHP=/ruta/al/php ./deploy.sh"
    exit 1
fi

PHP_VERSION=$("$PHP" -r 'echo PHP_VERSION;')
echo "▸ PHP: $PHP_VERSION ($PHP)"
echo "▸ App URL: $APP_URL"
echo ""

# 1. git pull
echo "→ git pull origin main"
git pull origin main
echo ""

# 2. composer (opcional con --quick)
if [ "$QUICK_MODE" -eq 0 ]; then
    echo "→ composer install --no-dev --optimize-autoloader"
    "$PHP" "$COMPOSER" install --no-dev --optimize-autoloader --no-interaction
    echo ""
else
    echo "→ skip composer (--quick)"
    echo ""
fi

# 3. reset OPcache vía web (CLI y web tienen caches separados en Hostinger)
echo "→ reset OPcache (vía HTTP)"
RESET_FILE="public_html/_opcache_reset.php"
RESET_TOKEN=$(date +%s)$RANDOM
cat > "$RESET_FILE" <<EOF
<?php
// Archivo temporal del deploy ($RESET_TOKEN), se borra automáticamente.
if (function_exists('opcache_reset') && opcache_reset()) {
    echo 'OK';
} else {
    echo 'NOOP';
}
EOF
RESET_RESPONSE=$(curl -sS --max-time 10 "$APP_URL/_opcache_reset.php" || echo "FAIL")
rm -f "$RESET_FILE"
if [ "$RESET_RESPONSE" = "OK" ]; then
    echo "  ✓ opcache reset"
elif [ "$RESET_RESPONSE" = "NOOP" ]; then
    echo "  · opcache no estaba habilitado (no hace falta reset)"
else
    echo "  ⚠ respuesta inesperada del reset: $RESET_RESPONSE"
fi
echo ""

# 4. verificar healthcheck
echo "→ verificar healthcheck"
HTTP_CODE=$(curl -sS -o /tmp/.health_resp -w "%{http_code}" "$APP_URL/api/health" || echo "000")
HEALTH_BODY=$(cat /tmp/.health_resp 2>/dev/null || echo "")
rm -f /tmp/.health_resp
if [ "$HTTP_CODE" = "200" ]; then
    echo "  ✓ /api/health → 200"
    echo "  · response: $HEALTH_BODY"
else
    echo "  ✗ /api/health → $HTTP_CODE"
    echo "  · response: $HEALTH_BODY"
    exit 1
fi

# 5. chequeo extra: las rutas HTML protegidas tienen que dar 302 (redirect a /login)
echo ""
echo "→ verificar rutas HTML (esperado: 302 a /login sin auth)"
for path in "/dashboard" "/giftcards" "/admin/establishments"; do
    CODE=$(curl -sS -o /dev/null -w "%{http_code}" "$APP_URL$path")
    if [ "$CODE" = "302" ]; then
        echo "  ✓ $path → 302"
    else
        echo "  ⚠ $path → $CODE (esperaba 302)"
    fi
done

echo ""
echo "Deploy OK ✓"
