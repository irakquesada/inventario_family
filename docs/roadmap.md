# Hoja de ruta

## Fase 0 — Fundamentos

- [x] Proyecto Laravel y configuración de marca.
- [x] Inicio/cierre de sesión sin registro público.
- [x] Roles `admin` y `staff` con permisos granulares.
- [x] Documentación arquitectónica.
- [x] Gestión de usuarios desde el panel.
- [x] Auditoría de entradas y salidas de la aplicación.
- [x] Auditoría general de operaciones y CI.
- [x] Activación comercial de módulos protegida por propietario y segunda verificación.
- [ ] Instalador privado para provisionar nuevos clientes.

## Fase 1 — Inventario esencial

- [x] Modelo inicial de categorías, marcas, productos y almacenes.
- [x] Libro de movimientos y saldo transaccional.
- [x] Listado reactivo y búsqueda de productos.
- [x] CRUD reactivo de categorías, marcas, almacenes y productos.
- [x] Entradas, salidas y ajustes con historial y usuario responsable.
- [x] Transferencias atómicas entre almacenes.
- [x] Preferencia por producto y alerta interna al cruzar el stock mínimo.
- [x] Consulta rápida de productos con stock agregado bajo.
- [x] Canal Telegram configurable y tolerante a fallos.
- [x] Preferencias individuales de canal y tipo de notificación.
- [x] Historial personal consultable de notificaciones vistas y pendientes.
- [x] Importación CSV y exportación CSV, Excel y PDF de productos.
- [x] Lectura de códigos de barras mediante lector físico y cámara.
- [x] Generación e impresión de etiquetas con códigos de barras.
- [x] Emisión de eventos en tiempo real con Reverb.

## Fase 2 — Inventario avanzado

- [x] Conteos físicos por almacén con fotografía de existencias.
- [x] Captura responsive con buscador y lector de códigos.
- [x] Identidad y fecha del usuario que cuenta cada producto.
- [x] Conciliación atómica y movimientos de ajuste auditables.
- [x] Aprobación administrativa, cancelación e historial.
- [x] Proveedores y referencias de compra.
- [x] Lotes, fechas de caducidad y rotación FEFO.
- [x] Órdenes de compra y recepción parcial.

## Después

## Fase 3 — Reservas

- [x] Recursos reservables con capacidad y estado.
- [x] Horario semanal con múltiples turnos y excepciones por fecha.
- [x] Horarios nocturnos que finalizan al día siguiente.
- [x] Agenda diaria responsive y flujo operativo de estados.
- [x] Prevención transaccional de solapamientos.
- [x] Disponibilidad y solicitud pública desde la landing.
- [ ] Recordatorios automáticos de reservas.

## Fase 4 — Landing y catálogo público

- [x] Landing responsive con contenido autoadministrable.
- [x] Datos de contacto, ubicación, horarios partidos y redes.
- [x] Productos destacados vinculados al inventario.
- [x] Fotografías y carrusel de productos destacables desde su ficha.
- [x] Testimonios manuales y sección de empleo opcional.
- [x] Invitaciones QR temporales, formulario público y moderación de reseñas.
- [x] Solicitud pública de reserva con asignación automática.
- [x] Carta o catálogo completo por categorías, con búsqueda y filtros.
- [x] Política de privacidad y aviso legal editables.

## Después

Landing/catálogo, personal y venta, en ese orden.

## Fase 5 — Personal y asistencia

- [x] Fichas de empleados vinculables a cuentas de usuario.
- [x] Catálogo administrable de puestos y sincronización con cuentas.
- [x] Fichaje personal de entrada y salida con historial y auditoría.
- [x] Horarios laborales individuales con descansos, turnos partidos y nocturnos.
- [x] Consulta personal responsive, actualización periódica e impresión del horario.
- [x] Panel administrativo de asistencia, incidencias, correcciones y exportación.
- [x] Vacaciones, permisos, bajas y descansos con solicitud y aprobación.
- [ ] Contratos y documentos laborales (aplazado hasta completar las fases comerciales).
- [ ] Resumen mensual para preparación de nómina (aplazado hasta completar las fases comerciales).

## Próximas prioridades acordadas

1. Recordatorios de reservas.
2. Recuperación segura de contraseña.
3. Instalador privado para nuevas implantaciones.
4. Pedidos públicos y tienda en línea.
5. Integración final con reseñas de Google.
6. Documentos laborales, contratos y resumen mensual de nómina.

## Fase 6 — Ventas y pedidos

- [x] Módulo comercial activable y permisos separados de consulta, venta, gestión y reembolso.
- [x] TPV responsive con buscador, categorías, lector físico, carrito, cliente opcional y formas de pago.
- [x] Ventas numeradas con fotografía de producto, precio y coste.
- [x] Descuento transaccional de existencias y consumo FEFO de lotes.
- [x] Historial, responsable, estados, ticket imprimible y auditoría.
- [x] Reembolsos autorizados con movimientos compensatorios y restauración de los lotes originales.
- [x] Actualización en tiempo real de inventario y ventas mediante Reverb.
- [ ] Pedidos públicos desde el catálogo, seguimiento y avisos al cliente.
- [ ] Preparación, recogida, consumo en local y entrega.
- [ ] Panel de métricas, cierre de caja y exportaciones comerciales.
- [ ] Pasarela de pago externa.
