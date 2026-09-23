# Arquitectura inicial

## Principios

1. Despliegue independiente por cliente.
2. Marca y configuración fuera de la lógica del dominio.
3. Monolito modular antes de considerar servicios separados.
4. Permisos granulares; los roles son agrupaciones configurables.
5. Todo cambio de stock produce un movimiento auditable.
6. Operaciones de inventario protegidas por transacciones y bloqueos de fila.
7. Interfaces responsive y accesibles, mejorables progresivamente como PWA.

## Dominios previstos

- Core: usuarios, permisos, configuración, ubicaciones y auditoría.
- Inventory: catálogo, almacenes, movimientos, existencias, lotes y alertas.
- Reservations: recursos, disponibilidad, horarios y bloqueos.
- Catalog: carta, catálogo público y venta.
- Content: landing administrable.
- People: asistencia, turnos, ausencias y documentos laborales.

## Inventario actual

- `products`: identidad comercial y logística del producto.
- `categories`: árbol de categorías mediante `parent_id`.
- `brands`: marcas reutilizables.
- `warehouses`: almacenes o locales.
- `inventory_balances`: saldo materializado por producto y almacén.
- `stock_movements`: libro inmutable de entradas y salidas.
- `inventory_lots`: existencia por producto, almacén, número de lote y caducidad.
- `stock_movement_lots`: distribución firmada de cada movimiento entre sus lotes.

`RecordStockMovement` bloquea el saldo afectado durante la transacción, rechaza cantidades cero y evita existencias negativas. `TransferStock` bloquea origen y destino en orden estable y registra una salida y una entrada dentro de una única transacción. Ambos movimientos comparten `operation_reference`; si cualquiera falla, toda la transferencia se revierte.

### Lotes, caducidad y FEFO

El control por lotes se activa por producto. Cuando está activo, ninguna entrada, salida, transferencia o conciliación puede modificar el saldo sin crear también sus asignaciones en `stock_movement_lots`; la suma de lotes del producto y almacén se mantiene igual al saldo general. Los productos existentes con stock se convierten mediante una asignación inicial auditable que distribuye el saldo entre almacenes sin aumentarlo de nuevo.

Las entradas exigen número de lote y, si el producto lo indica, fecha de caducidad. Las salidas ordinarias aplican FEFO: consumen primero la fecha útil más próxima y nunca utilizan lotes caducados. Los ajustes negativos de inventario y las transferencias físicas sí pueden afectar lotes caducados porque representan correcciones o desplazamientos reales, no consumo o venta. Una transferencia conserva número, caducidad y cantidades de cada lote en el destino.

La pantalla de lotes separa disponibles, próximos a caducar, caducados y vacíos. El umbral de aviso se configura por producto. La tarea `inventory:notify-expiring-lots`, programada diariamente a las 08:00, entrega una alerta a cada administrador activo mediante base de datos y Telegram cuando esté habilitado. `expiry_notified_at` evita duplicados; si un lote vacío vuelve a recibir existencias, queda preparado para un nuevo aviso.

## Tiempo real

Livewire cubre las interacciones sin recarga completa y Reverb/Echo sincroniza las sesiones concurrentes. Cada movimiento confirmado emite `InventoryChanged` después del commit a un canal privado autorizado mediante `inventory.view`. Dashboard, productos, movimientos, transferencias, lotes y recepciones abiertas vuelven a consultar sus datos al recibirlo. La emisión inmediata está aislada con manejo de errores: una caída del WebSocket nunca revierte ni oculta el resultado de la transacción de stock.

## Alertas y auditoría

Los productos pueden activar `notify_low_stock`. La alerta se genera una sola vez al cruzar desde una existencia superior hasta el mínimo configurado, lo que evita mensajes repetidos mientras el producto continúa bajo. Se entrega al panel mediante notificaciones de base de datos y, si está activado, también mediante Telegram; el canal puede reutilizarse posteriormente por asistencia y otros módulos.

El canal Telegram usa la API `sendMessage` con HTML escapado, timeout, reintentos y registro de fallos. Una caída de Telegram nunca revierte un movimiento de inventario ya confirmado. Se activa únicamente mediante `TELEGRAM_NOTIFICATIONS_ENABLED`, `TELEGRAM_BOT_TOKEN` y `TELEGRAM_CHAT_ID` en `.env`.

En producción, el planificador requiere ejecutar `php artisan schedule:run` cada minuto mediante cron o un servicio equivalente. Esta única entrada activa tanto las alertas actuales como las tareas programadas que se incorporen después.

Los eventos de autenticación exitosos crean registros inmutables de entrada y salida con usuario, fecha, IP, sesión y agente del dispositivo. Su consulta requiere el permiso `audit.view`.

`activity_logs` complementa ese registro y el libro de stock con una auditoría administrativa transversal. Observa altas, cambios y bajas de usuarios, catálogo, proveedores, conteos y compras; guarda el responsable, fecha, IP, entidad y diferencias anterior/posterior. Las credenciales y secretos se excluyen explícitamente. Los movimientos internos de saldo y lote no se duplican aquí porque su historial especializado aporta una trazabilidad más útil.

El flujo de integración continua en `.github/workflows/ci.yml` valida `composer.json`, instala dependencias reproducibles desde los archivos de bloqueo, comprueba Pint, ejecuta toda la suite sobre SQLite en memoria y compila los recursos frontend. Se ejecuta en cada envío y solicitud de cambios con permisos de solo lectura.

## Experiencia de usuario

El tema admite `system`, `light` y `dark`, se aplica antes del primer render y persiste en el navegador. Los movimientos se registran desde una pantalla operativa separada de la edición del producto para reducir errores; cada registro conserva el usuario autenticado, almacén, motivo, cantidad y saldo resultante.

Al crear un producto se puede indicar una existencia inicial opcional. No se escribe directamente en el saldo: se registra dentro de la misma operación como un movimiento `opening_balance`, con almacén, usuario y motivo. Después del alta, cualquier cambio de stock debe realizarse desde Movimientos.

## Intercambio masivo de catálogo

Los productos pueden exportarse e importarse mediante CSV UTF-8 separado por punto y coma, compatible con las configuraciones habituales de Excel en español. El SKU actúa como clave estable: una fila con SKU existente actualiza la ficha y una nueva la crea. Todo el archivo se valida antes de escribir, con un máximo de 2.000 filas por operación, control de códigos de barras duplicados y transacción única. Las celdas exportadas se neutralizan frente a fórmulas de hoja de cálculo.

La exportación también ofrece un libro Excel tipado, con filtros, encabezado inmóvil y columnas preparadas para ordenar, y un informe PDF apaisado y paginado. La búsqueda de productos admite lectores físicos que emulan teclado y lectura con la cámara mediante ZXing; la cámara requiere HTTPS salvo en `localhost` y se detiene al leer o cerrar el diálogo.

La importación de catálogo no acepta cantidades. El stock solo cambia mediante servicios de movimientos para conservar almacén, usuario, motivo y trazabilidad.

## Conteos físicos y conciliación

`inventory_counts` representa una sesión de conteo para un almacén y `inventory_count_items` conserva la cantidad teórica al abrir, la cantidad física, el usuario y la hora de captura. Solo puede existir un conteo abierto por almacén; la creación bloquea la fila del almacén para evitar aperturas simultáneas.

El saldo teórico guardado es una fotografía informativa. Al finalizar, `CompleteInventoryCount` bloquea el conteo, exige que todos los productos hayan sido capturados y vuelve a leer los saldos actuales. Cada saldo se lleva hasta la cantidad física mediante un movimiento `stocktake_adjustment`, agrupado con la referencia UUID del conteo. Toda la conciliación se ejecuta en una sola transacción: si falla un ajuste, se revierten todos y el conteo permanece abierto.

El permiso `inventory.count` permite capturar cantidades. `inventory.count.approve` queda reservado inicialmente al rol administrador y permite abrir, cancelar y conciliar. Las cancelaciones conservan usuario y fecha y nunca modifican stock.

## Proveedores y condiciones de suministro

`suppliers` conserva identidad fiscal, contacto, dirección, notas y estado operativo. `product_supplier` modela la relación comercial: referencia externa, coste unitario, cantidad mínima, plazo de entrega y notas. Un producto puede tener múltiples proveedores, pero solo una relación puede marcarse como preferente; el cambio de preferencia desmarca las demás dentro de la misma transacción.

El coste de proveedor no sobrescribe automáticamente `products.cost`: representa una condición de compra y servirá como propuesta al construir una orden. Esto evita alterar la valoración general del catálogo por editar una oferta. Los proveedores vinculados no se eliminan; se desactivan para conservar la historia y preparar las referencias de futuras órdenes de compra.

## Órdenes de compra y recepciones

`purchase_orders` conserva proveedor, almacén de destino, fechas, estado y responsables. Sus líneas fotografían la referencia del proveedor, cantidad y coste acordado para que los cambios posteriores en la ficha comercial no alteren una orden histórica. El flujo inicial es `draft` → `ordered` → `partially_received` → `received`; una orden sin recepciones puede cancelarse sin modificar existencias.

Las recepciones son registros independientes en `purchase_receipts`, por lo que una orden admite tantas entregas parciales como sean necesarias. `ReceivePurchaseOrder` bloquea la orden y cada línea, impide superar la cantidad pendiente y ejecuta todos los movimientos dentro de una única transacción. Un fallo en cualquier producto revierte la recepción completa. Los productos con lotes exigen sus datos de lote y caducidad y reutilizan `LotStock`; el resto utiliza el libro general. Cada movimiento incluye las referencias de orden y recepción en sus metadatos y comparte el número de recepción como referencia operativa.

El coste recibido queda fotografiado en la recepción, pero no modifica automáticamente el coste maestro del producto. La futura política de valoración podrá decidir entre último coste, coste medio u otro método sin perder información histórica.

## Reservas y disponibilidad

`reservation_resources` representa aquello que no puede asignarse simultáneamente: una mesa, sala, pista o profesional. `business_hours` admite múltiples intervalos por día para jornadas partidas; la ausencia de intervalos significa que el día está cerrado. `business_closures` sustituye el horario completo en fechas concretas para cierres o aperturas especiales. Si la hora de cierre es igual o anterior a la apertura, el intervalo termina al día siguiente. La configuración rechaza turnos solapados, incluso cuando el conflicto atraviesa la medianoche o el límite semanal.

`CreateReservation` valida contacto, capacidad y horario antes de bloquear la fila del recurso. Dentro de la transacción vuelve a comprobar solapamientos contra reservas `pending`, `confirmed` o `seated` usando intervalos semiabiertos: dos reservas pueden tocarse exactamente en el final/inicio, pero nunca compartir tiempo. Cancelaciones, ausencias y reservas completadas liberan disponibilidad sin perder su historial.

El flujo operativo es `pending` → `confirmed` → `seated` → `completed`, con salidas controladas hacia `cancelled` o `no_show`. Staff puede consultar y operar la agenda; la configuración de recursos, horarios y excepciones requiere `reservations.configure` y se asigna al administrador.

### Solicitud pública

La landing no expone nombres internos de mesas o recursos. `CreatePublicReservation` filtra por capacidad, intenta primero el recurso más pequeño adecuado y reutiliza el bloqueo transaccional de `CreateReservation`. La solicitud nace como `pending` y el equipo recibe una notificación interna y por Telegram cuando esté activo. El formulario exige consentimiento y un medio de contacto, limita intentos por IP y contiene un honeypot; la validación de disponibilidad siempre se repite en servidor.

## Contenido público

`site_settings` centraliza portada, presentación, contacto, ubicación, redes y activación de secciones. `is_sellable` expresa que un producto podrá formar parte de carta, catálogo o venta; `featured_on_landing` es independiente y controla su aparición promocional. La imagen pública opcional se guarda en el disco `public` y alimenta tarjetas y un carrusel globalmente desactivable, evitando mantener un segundo catálogo desconectado. `testimonials` conserva comentarios editoriales con puntuación, visibilidad y orden. Solo `content.manage` permite publicar cambios.

La carta pública consulta exclusivamente productos activos con `is_sellable`, admite búsqueda y filtrado por categorías y subcategorías, y nunca utiliza el saldo de inventario como criterio de publicación. Esto evita exponer cantidades internas y permite reutilizar el mismo catálogo para futuros canales de venta.

## Personal y asistencia

`employees` separa la ficha laboral de la cuenta de acceso, aunque toda cuenta creada desde administración genera y sincroniza automáticamente su ficha básica. Los puestos se normalizan en `job_positions` y nunca se capturan como texto libre. `employee_work_periods` define un horario semanal distinto por empleado: admite varios tramos por día, días sin tramos como descanso y turnos que cruzan medianoche. La configuración completa se sustituye en una transacción y rechaza solapamientos incluso entre el domingo y el lunes siguiente.

`time_entries` conserva cada entrada y salida, el usuario responsable, origen e IP. El fichaje bloquea la ficha del empleado y solo admite una jornada abierta, evitando duplicados por doble pulsación o solicitudes concurrentes. Los empleados consultan su propio historial; la configuración laboral requiere `people.manage` y no está disponible para el propio trabajador.

El informe administrativo compara por empleado y fecha los tramos planificados con los fichajes reales. Señala ausencias de fichaje, jornadas abiertas, entrada tardía con cinco minutos de tolerancia, horas insuficientes y trabajo en días de descanso. Las correcciones exigen un motivo y crean un registro inmutable en `time_entry_adjustments` con valores anteriores y nuevos, administrador e IP antes de actualizar el fichaje operativo. El mismo informe filtrado alimenta las exportaciones Excel y PDF.

`absence_types` normaliza vacaciones, permisos, bajas y descansos, indicando si descuentan saldo o exigen justificante. Los documentos se guardan en el disco privado y solo pueden descargarlos el propio empleado o un administrador de personal. Las solicitudes admiten días completos o una fracción horaria, rechazan solapamientos y siguen el flujo `pending` → `approved`/`rejected`, con cancelación personal mientras sigan pendientes. Las vacaciones aprobadas bloquean transaccionalmente el saldo anual y se incorporan al informe de asistencia como ausencia justificada, eliminando falsos avisos de falta de fichaje.
