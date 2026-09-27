# Chetaco VLC

Núcleo modular para inventario, operaciones, reservas, catálogo y personal. La primera entrega prioriza el inventario y está diseñada para funcionar en móvil y ordenador.

Incluye además un módulo de ventas/TPV activable: carrito táctil, cobro por almacén, tickets, historial, consumo FEFO y reembolsos auditables que restauran existencias sin alterar el movimiento original.

## Requisitos locales

- PHP 8.3 o superior (desarrollo actual: 8.4)
- Composer 2
- Node.js 20 o superior
- MySQL 8 para desarrollo integrado/producción; SQLite es suficiente para pruebas

## Puesta en marcha

```bash
cp .env.example .env
composer install
php artisan key:generate
npm install
npm run build
php artisan migrate --seed
php artisan storage:link
composer run dev
```

Para crear el primer administrador, ejecuta el comando interactivo siguiente. La contraseña se solicita de forma oculta y no se guarda en `.env` ni en el historial del shell:

```bash
php artisan app:create-admin
```

## Conectar MySQL

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=chetacovlc
DB_USERNAME=chetacovlc
DB_PASSWORD=una-clave-segura
```

La aplicación no crea la base ni el usuario de MySQL automáticamente. Esto evita interferir con otros proyectos instalados en el mismo equipo.

## Protección del inicio de sesión con Turnstile

El formulario de acceso puede protegerse con Cloudflare Turnstile. Crea un widget para el dominio definitivo, copia la clave pública y la secreta al `.env` del servidor y actívalo:

```dotenv
TURNSTILE_ENABLED=true
TURNSTILE_SITE_KEY=clave-publica-del-widget
TURNSTILE_SECRET_KEY=clave-secreta-del-widget
```

La respuesta del widget se valida en el servidor antes de comprobar las credenciales. Si Cloudflare no responde o la configuración está incompleta, el acceso se rechaza de forma segura. Para desarrollo local puede mantenerse `TURNSTILE_ENABLED=false`. No guardes claves reales en `.env.example` ni en Git.

## Personalización para otro cliente

El nombre, identificador, color, moneda, idioma y zona horaria se controlan desde `.env`. La configuración de marca está centralizada en `config/brand.php`; no se deben escribir nombres comerciales directamente en vistas o clases.

El propietario técnico se define con `PLATFORM_OWNER_EMAIL`. Únicamente esa cuenta, estando activa y con rol `admin`, puede abrir **Plataforma**. El módulo exige volver a introducir la contraseña y el desbloqueo caduca a los 15 minutos por defecto. Desde allí pueden activarse o desactivarse Inventario, Landing, Reservas y Personal sin eliminar datos. Las rutas deshabilitadas también quedan bloqueadas; no se trata únicamente de ocultar enlaces.

## Importación de productos

La propia pantalla de productos incluye una guía detallada y una plantilla CSV limpia. La primera fila de cabeceras debe conservarse, el archivo admite coma o punto y coma como separador y debe guardarse en UTF-8. El SKU decide si se crea o actualiza el producto. La importación nunca cambia existencias: el stock se registra mediante movimientos auditables.

## Productos en la landing

`Disponible para venta` y `Mostrar en la landing` son decisiones independientes. La primera prepara el producto para el futuro catálogo, menú digital o tienda; la segunda permite destacarlo públicamente como contenido promocional.

Cada producto destacado puede tener una imagen JPG, PNG o WebP de hasta 5 MB. Las imágenes se guardan en `storage/app/public/products`, por lo que cada instalación debe ejecutar una vez:

```bash
php artisan storage:link
```

Desde la configuración de la landing se puede activar o desactivar el carrusel completo sin retirar los productos seleccionados. El carrusel utiliza únicamente los productos destacados que tienen imagen; los demás continúan apareciendo en la cuadrícula de productos.

## Reseñas mediante QR

El administrador puede crear desde la configuración de la landing invitaciones temporales y revocables, con fecha de caducidad y número máximo de respuestas. El enlace completo solo se muestra al generarlo; la base de datos conserva únicamente su huella criptográfica.

Las reseñas recibidas quedan pendientes hasta que un administrador las aprueba. El correo nunca se publica, la foto es opcional y la autorización para recibir novedades se solicita separadamente del consentimiento necesario para gestionar la reseña. Las fotos se guardan en `storage/app/public/testimonials`, por lo que también requieren `php artisan storage:link`.

## Arquitectura

Se usa un monolito modular. Los módulos comparten autenticación, permisos, auditoría, colas y eventos, pero las reglas del dominio se mantienen separadas. El stock se modifica exclusivamente mediante movimientos transaccionales y nunca editando el saldo sin historial.

Consulta [docs/architecture.md](docs/architecture.md) y [docs/roadmap.md](docs/roadmap.md).

## Telegram

Las alertas internas funcionan sin servicios externos. Para añadir Telegram, configura las siguientes variables solo en `.env`:

```dotenv
TELEGRAM_NOTIFICATIONS_ENABLED=true
TELEGRAM_BOT_TOKEN=
TELEGRAM_CHAT_ID=
```

No confirmes la activación hasta haber introducido token y destinatario. El canal externo es tolerante a fallos: los movimientos de inventario no dependen de la disponibilidad de Telegram.

Puedes validar la configuración sin mostrar las credenciales con:

```bash
php artisan app:test-telegram
```

Cada usuario puede elegir desde **Mis notificaciones** qué avisos recibe en el panel, por correo o Telegram. El Chat ID es individual y se almacena cifrado. El token del bot continúa siendo una configuración global del servidor y nunca se introduce desde la interfaz.

Las notificaciones no se eliminan al marcarlas como vistas. La sección **Notificaciones** conserva el historial personal, fecha de recepción y lectura, y permite buscar y filtrar por estado o tipo.

## Consulta rápida de stock bajo

El listado de productos incluye un acceso directo a los productos activos cuya existencia total, sumada entre todos los almacenes, es menor o igual que su stock mínimo. La tabla muestra la existencia y el mínimo para facilitar la preparación de pedidos a proveedores.

## Operación periódica

Laravel concentra todas las tareas programadas en una sola entrada de cron. En el servidor debe existir:

```cron
* * * * * cd /var/www/floopware/chetacovlc && php artisan schedule:run >> /dev/null 2>&1
```

La comprobación de lotes próximos a caducar se ejecuta diariamente a las 08:00 y avisa una sola vez a los administradores activos. Puede ejecutarse manualmente con:

```bash
php artisan inventory:notify-expiring-lots
php artisan schedule:list
```

La analítica pública conserva los eventos durante 13 meses y elimina automáticamente los más antiguos el primer día de cada mes. Registra páginas, clics e impresiones visibles de producto, pero no guarda direcciones IP en claro ni textos introducidos por visitantes. Los administradores autorizados pueden consultar filtros y exportar CSV desde **Analítica**.

## Correos de reservas

Las credenciales del proveedor de correo siempre se configuran en `.env`. Desde **Reservas → Configuración → Correos al cliente** se puede cambiar el nombre y dirección visibles del remitente y editar las plantillas de solicitud recibida, confirmación y cancelación. Si no se personalizan, la aplicación utiliza textos seguros predeterminados y la identidad global `MAIL_FROM_*`.

La dirección remitente personalizada debe estar autorizada por el proveedor SMTP. Los mensajes al cliente se envían mediante la cola, por lo que `inventario-pro-queue.service` debe permanecer activo.

## Tiempo real

Livewire resuelve las operaciones de cada usuario sin recargar páginas. Reverb añade sincronización entre dispositivos: una entrada, salida, transferencia, recepción de compra o conciliación actualiza las pantallas abiertas de otros usuarios.

En desarrollo, además del servidor web, debe mantenerse Reverb en ejecución:

```bash
php artisan reverb:start
```

La activación se controla con `BROADCAST_CONNECTION=reverb` y `VITE_REALTIME_ENABLED=true`. Si Reverb no está disponible, los movimientos se confirman igualmente y el fallo de emisión se registra en el log. Después de modificar variables `VITE_*` hay que recompilar con `npm run build`. Tras un despliegue con Reverb activo puede reiniciarse limpiamente con:

```bash
php artisan reverb:restart
```

### Producción con Apache y systemd

No ejecutes `composer run dev`, `npm run dev` ni `php artisan serve` en producción. Si existe `public/hot`, Laravel intentará cargar Vite desde el puerto 5173 aunque el frontend esté compilado. El despliegue debe eliminarlo y generar archivos estáticos:

```bash
rm -f public/hot
npm ci
npm run build
php artisan optimize
```

Para `https://floopware.com/inventario_pro`, configura el `.env` antes de compilar, porque las variables `VITE_*` se incorporan al JavaScript generado:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://floopware.com/inventario_pro
ASSET_URL=https://floopware.com/inventario_pro

BROADCAST_CONNECTION=reverb
QUEUE_CONNECTION=database
VITE_REALTIME_ENABLED=true
REVERB_APP_ID=valor-aleatorio
REVERB_APP_KEY=valor-aleatorio
REVERB_APP_SECRET=valor-aleatorio
REVERB_SERVER_HOST=127.0.0.1
REVERB_SERVER_PORT=8080
REVERB_HOST=floopware.com
REVERB_PORT=443
REVERB_SCHEME=https
REVERB_ALLOWED_ORIGINS=floopware.com
VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST="${REVERB_HOST}"
VITE_REVERB_PORT="${REVERB_PORT}"
VITE_REVERB_SCHEME="${REVERB_SCHEME}"
```

El repositorio incluye unidades listas en `deploy/systemd`, la entrada de cron en `deploy/cron` y el proxy WebSocket de ejemplo en `deploy/apache`. Instálalos con:

```bash
sudo cp deploy/systemd/inventario-pro-queue.service /etc/systemd/system/
sudo cp deploy/systemd/inventario-pro-reverb.service /etc/systemd/system/
sudo cp deploy/cron/inventario-pro /etc/cron.d/inventario-pro
sudo chmod 644 /etc/cron.d/inventario-pro
sudo systemctl daemon-reload
sudo systemctl enable --now inventario-pro-queue inventario-pro-reverb
```

Las directivas de `deploy/apache/reverb-proxy.conf.example` deben añadirse dentro del `VirtualHost *:443` de `floopware.com`. Luego valida y activa todo:

```bash
sudo a2enmod proxy proxy_http proxy_wstunnel
sudo apache2ctl configtest
sudo systemctl reload apache2
sudo systemctl status inventario-pro-queue inventario-pro-reverb --no-pager
php artisan schedule:list
```

Tras actualizar el código, reinicia los procesos de larga duración para que carguen la nueva versión:

```bash
sudo systemctl restart inventario-pro-queue inventario-pro-reverb
```

## Comprobaciones del proyecto

Antes de integrar cambios importantes:

```bash
vendor/bin/pint --test
php artisan test
npm run build
```

GitHub Actions ejecuta estas comprobaciones automáticamente en cada envío o solicitud de cambios.
