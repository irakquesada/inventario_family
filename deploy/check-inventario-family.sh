#!/usr/bin/env bash
set -euo pipefail

domain="${1:-inventario-family.floopware.com}"

echo "== Servicios =="
systemctl --no-pager --full status inventario-family-queue inventario-family-reverb

echo "== Puerto Reverb local =="
ss -ltnp | grep '127.0.0.1:8081'

echo "== Laravel =="
cd /var/www/floopware/inventario_family
/usr/bin/php artisan about --only=environment
/usr/bin/php artisan schedule:list
/usr/bin/php artisan queue:monitor database:default --max=100

echo "== HTTP público =="
curl --fail --silent --show-error --location --output /dev/null --write-out 'HTTPS %{http_code}\n' "https://${domain}/"

echo "Comprobación terminada. Para validar WSS abre la aplicación y revisa DevTools > Network > WS."
