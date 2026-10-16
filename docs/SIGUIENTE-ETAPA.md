# Siguiente etapa propuesta

Esta lista es una propuesta de evolución, no funcionalidades terminadas.

## Antes de publicar

Confirmar el dominio y características del cPanel; instalar dependencias y verificar `astro build`; validar MySQL; completar los datos corporativos; aprobar términos de contratación; resolver los puntos del programa de Turquía; reemplazar fotografías recortadas por originales de mayor resolución. Después hacer pruebas de envío, persistencia y acceso en un entorno de ensayo del hosting.

## Si se mantiene PHP sencillo

Separar los renderizadores de páginas en componentes Astro más pequeños al crecer el diseño. Convertir los bloques específicos de Turquía a un modelo de contenido genérico. Incorporar editor de itinerarios y vuelos, alta de nuevos programas, biblioteca de imágenes y documentos con validación MIME/tamaño y almacenamiento fuera de rutas ejecutables. Añadir paginación del panel, políticas de retención, roles y recuperación/segundo factor de acceso.

El siguiente paso importante para un editor no técnico es la **publicación automática**: guardar un borrador, aprobarlo, compilar fuera de cPanel y subir la salida comprobada. No resolverlo ejecutando comandos proporcionados por el usuario desde PHP ni colocando tokens privados en JavaScript.

## Si se elige Laravel

Mantener Astro para las páginas públicas y migrar la API/panel a Laravel con los recursos: programas, días, vuelos, precios, salidas, documentos, consultas y usuarios. Usar autenticación mantenida, validaciones, políticas de autorización y migraciones. Configurar el directorio público de forma segura y conservar el build de Astro fuera del hosting. Laravel no elimina la necesidad de recompilar las páginas Astro cuando cambia contenido estático.

El requisito de versión PHP/extensiones debe confirmarse con el alojamiento antes de elegir la versión del framework. No se incluyó un esqueleto Laravel sin instalar ni probar para presentarlo como funcional.

## Conversiones y gestión comercial

Agregar SMTP cuando exista un remitente institucional y credenciales configuradas de forma privada; confirmar primero que el lead quede persistido y separar los fallos de notificación de los de recepción. Un panel con estados no equivale a un CRM completo. Los mensajes de WhatsApp actuales se abren de forma manual; no existe integración de WhatsApp Business API.

Medición de conversiones, Meta Pixel, analítica y captación para campañas requieren una decisión explícita sobre consentimiento y privacidad. Los parámetros UTM se guardan únicamente con la consulta enviada, no como seguimiento automático.

## Pagos, reservas e inventario

Fuera del alcance actual: confirmación de cupos, pagos online, abonos, documentos de pasajeros, firma contractual, emisión aérea, hoteles o integraciones con proveedores. No instalar un botón de pago antes de resolver disponibilidad, términos, moneda, responsabilidades, conciliación y atención de cambios/cancelaciones.
