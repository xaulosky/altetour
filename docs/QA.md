# Verificación local de la primera versión

## Resultado

Se completaron **107 comprobaciones de interfaz** y **25 comprobaciones del backend**, todas aprobadas en el entorno local. Son comprobaciones concretas del prototipo, no una auditoría de seguridad, certificación de accesibilidad ni garantía de funcionamiento en cualquier hosting.

Los resultados individuales están en `docs/verificacion/`. Se adjuntan capturas de inicio, página de Turquía y panel en `docs/vistas/`.

## Interfaz

Se utilizó Chromium mediante Playwright con la vista autocontenida, que comparte datos, renderizadores, estilos y JavaScript con el código de Astro.

- Nueve rutas navegables en anchos de 1440, 1024, 768, 390 y 360 píxeles: sin desborde horizontal de la página y con un único encabezado principal por ruta.
- Navegación móvil, buscador y filtros, estados vacíos, diez días del itinerario, expansión/contracción, afiche en ventana modal y selección del programa en la consulta.
- En la demostración, el formulario identifica expresamente que no almacena datos; prepara un mensaje para WhatsApp, cuyo envío depende del visitante. No se simula un registro exitoso en el servidor.
- Recursos visuales locales y ausencia de errores JavaScript durante las comprobaciones. Revisión visual adicional de capturas de escritorio y móvil.

Los submenús horizontales pueden desplazarse dentro de su propio contenedor en pantallas pequeñas; no deben ensanchar la página. Las barras de cotización y WhatsApp se mantienen fijas en móvil y pueden aparecer a la altura del primer viewport en una captura de página completa.

## Backend

PHP 8.4.24, servidor integrado local y **adaptador JSON privado para desarrollo**. Se comprobó la sintaxis de los once archivos PHP distribuidos, incluidos los instaladores CLI.

Las pruebas cubren CSRF, validación de correo y consentimiento, límites de tamaño, registro de una consulta, reintento sin duplicación, rechazo de contenido distinto con la misma clave, validación del programa, autenticación, cierre de sesión, controles de acceso, escape de contenido, exportación CSV con neutralización de fórmulas, cambio de estado, edición y exportación del programa y eliminación con confirmación. Las pruebas no envían correos ni mensajes externos.

La cuenta y la consulta utilizadas en las pruebas son ficticias. Las credenciales, sesiones, archivos de persistencia y registros locales **no se incluyen en la distribución**.

## Qué NO se verificó

1. **Compilación de Astro:** no fue posible instalar sus dependencias porque el entorno no resolvió el registro npm. La salida `preview/` se generó con el renderizador compartido y no representa un `astro build`. Es obligatorio ejecutar `npm install` y `npm run build` antes de publicar la distribución de Astro.
2. **MySQL/PDO:** el código del adaptador y el esquema están incluidos, pero no se ejecutaron con MySQL porque el entorno carece del controlador PDO MySQL. Probar instalación, consultas, índices, permisos y exportación en la versión concreta del servidor.
3. **Hosting final:** no hay un despliegue en cPanel, dominio ni GitHub. Deben comprobarse HTTPS, cookies seguras, rutas públicas/privadas, reescrituras de Apache, permisos, copias de seguridad y el formulario desde el dominio real.
4. **Integraciones y aprobaciones:** no hay SMTP, pasarela de pago, confirmación de reservas, envío automático de WhatsApp ni analítica externa. El contenido contractual, la privacidad y los puntos comerciales señalados siguen pendientes de aprobación.
5. **Cobertura de navegadores y carga:** no se ejecutaron pruebas en Safari/Firefox, de concurrencia intensiva, recuperación ante fallos de MySQL ni un análisis formal de accesibilidad o rendimiento.

## Repetir las pruebas opcionales

Las pruebas no son dependencias necesarias del sitio. Requieren Python con `requests`, `playwright` y Chromium instalado para Playwright, además de Node y PHP. Ejecutarlas en un entorno desechable de desarrollo, **nunca contra una base de producción**.

```bash
node scripts/build-preview.mjs
php backend/bin/install.php --demo
# Mantener este servidor abierto en una terminal:
php -S 127.0.0.1:8766 -t backend/public
# En otra terminal:
python scripts/test-backend.py
python scripts/test-frontend.py
```

La prueba del backend crea una cuenta de QA, usa datos ficticios y modifica/restaura la tarifa del programa. Para una instalación real, crear una configuración nueva y un administrador propio con los comandos documentados.

## Criterio de lanzamiento

Publicar únicamente después de compilar Astro, probar el adaptador MySQL en el hosting, aprobar contenido y privacidad, reemplazar las imágenes de baja resolución cuando se disponga de originales y comprobar el recorrido completo de una consulta de prueba. Mantener `PUBLIC_SITE_INDEXABLE=false` hasta entonces.
