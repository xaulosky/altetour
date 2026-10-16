# Instalación en cPanel

## Decisión de esta base

Astro produce archivos estáticos; PHP procesa las consultas y el panel. No se necesita un proceso Node persistente, Composer, colas ni Redis para esta entrega. Esta es una implementación PHP propia y pequeña; **no es Laravel**. Su mantenimiento exige conservar y revisar las validaciones y protecciones del código.

Si se decide utilizar Laravel, revisar primero PHP y extensiones del alojamiento. La documentación de Laravel 13 indica PHP 8.3 o superior y recomienda servir exclusivamente su directorio `public/`. No trasladar una aplicación Laravel completa al directorio público para hacerla funcionar.

Fuentes técnicas consultadas el 12/10/2026:
- Documentación oficial de despliegue de Astro: `https://docs.astro.build/en/guides/deploy/`
- Instalación de Astro: `https://docs.astro.build/en/install-and-setup/`
- Despliegue de Laravel 13: `https://laravel.com/docs/13.x/deployment`
- Administración de dominios cPanel: `https://docs.cpanel.net/cpanel/domains/domains/`

## 1. Verificar el alojamiento

Confirmar dominio y raíz documental, PHP 8.3 o superior, PDO MySQL, sesiones, acceso a MySQL/MariaDB con soporte del tipo JSON, certificado SSL y permisos de escritura en el directorio privado. El panel y el formulario deben probarse sobre HTTPS.

El adaptador MySQL de esta entrega no fue ejecutado en el entorno de preparación; debe ser validado en una base de ensayo del hosting antes de lanzar campañas.

## 2. Preparar la versión de Astro fuera del hosting

En `frontend/.env`:

```dotenv
SITE_URL=https://TU-DOMINIO.cl
PUBLIC_SITE_INDEXABLE=false
```

```bash
cd frontend
npm install
npm run build
cd ..
node scripts/package-cpanel.mjs
```

Tras la primera instalación, conservar el `package-lock.json` generado y utilizar `npm ci` para compilaciones reproducibles. Corregir cualquier error real del build antes de publicar. El render portable entregado no sustituye esta verificación.

## 3. Estructura en el servidor

Para un dominio principal cuyo directorio público sea `/home/USUARIO/public_html`:

```text
/home/USUARIO/
  altetour-app/          ← código y almacenamiento PRIVADOS
    bin/
    config/
      config.local.php
      programs.seed.json
      schema.sql
    src/
    storage/
  public_html/          ← únicamente contenido de deploy/public_html
    index.html
    assets/
    viajes/
    nosotros/
    ...otras páginas...
    api/index.php
    admin/index.php
    admin/admin.css
    _bootstrap.php
    .htaccess
```

Subir `deploy/altetour-app` FUERA de `public_html`. Subir el contenido de `deploy/public_html` al directorio público, incluyendo `.htaccess`.

El puente `_bootstrap.php` detecta esa disposición. Si el dominio adicional usa otra ubicación, configurar la variable de entorno `ALTE_APP_ROOT` con la ruta absoluta privada o ajustar la ruta fija de ese puente. No tomar la ruta desde parámetros HTTP. El código verifica que `storage/` no esté bajo `DOCUMENT_ROOT`.

Nunca subir a una ruta pública: `src/`, `config/`, `storage/`, SQL de instalación, `.env`, copias de la base de datos ni credenciales. Nunca cambiar permisos a 777.

## 4. Crear base y configuración

Desde cPanel, crear una base de datos y un usuario exclusivo para esta aplicación. Asignar los permisos necesarios para instalación y operación; después de instalar puede revisarse la reducción de privilegios de modificación de esquema.

Copiar `config/config.example.php` a `config/config.local.php` en la carpeta privada y completar:

```php
'environment' => 'production',
'app_key' => 'CLAVE_ALEATORIA_REAL_GENERADA_LOCALMENTE',
'storage_driver' => 'mysql',
'mysql' => [
    'host' => 'localhost',
    'port' => 3306,
    'database' => 'USUARIO_altetour',
    'username' => 'USUARIO_alte',
    'password' => 'CONTRASEÑA_REAL_DE_LA_BASE',
],
```

Generar la clave localmente, no compartirla en chats ni repositorios:

```bash
php -r 'echo bin2hex(random_bytes(32)), PHP_EOL;'
```

Con terminal disponible en el hosting:

```bash
cd /home/USUARIO/altetour-app
php bin/install.php
php bin/create-admin.php TU-CORREO-ADMINISTRATIVO
```

La contraseña administrativa se ingresa por entrada estándar. No hay cuenta por defecto ni formulario público de instalación. Comprobar propietario y permisos; directorios privados 700/750 y archivos sensibles 600/640, según el usuario y grupo efectivos de PHP del alojamiento.

## 5. Alternativa sin terminal en cPanel

Generar localmente un SQL que incluya estructura, programa y hash del administrador:

```bash
php backend/bin/make-install-sql.php TU-CORREO-ADMINISTRATIVO > instalacion-privada.sql
```

El programa solicita la contraseña por entrada estándar. Importar el resultado en la base correcta desde phpMyAdmin. **No subir ese SQL a `public_html` y eliminar la copia temporal tras utilizarla.** El archivo contiene un hash administrativo que también requiere protección. Configurar después `config.local.php` manualmente en la carpeta privada.

## 6. HTTPS y sesión

Activar el certificado y la redirección HTTPS en cPanel. En producción la cookie de sesión se marca `Secure`, `HttpOnly` y `SameSite=Lax`; el acceso por HTTP no debe utilizarse. El panel caduca por inactividad y regenera el identificador de sesión al autenticar.

No existe recuperación de contraseña por correo en esta versión. Para restablecer, ejecutar `create-admin.php` o volver a generar/importar el SQL de administrador de forma controlada. Considerar 2FA o protección adicional para una siguiente versión.

## 7. Pruebas mínimas en el hosting

- Cargar inicio, cada ruta directa, assets y un enlace inexistente; comprobar respuesta 404.
- Abrir el panel y confirmar que una persona no autenticada no puede listar/exportar consultas.
- Enviar una consulta real de prueba con consentimiento; verificar que aparece una sola vez y que el formulario devuelve referencia.
- Probar correo inválido, falta de consentimiento, token CSRF inválido y reintento con la misma clave.
- Cambiar estado y comprobar persistencia tras recargar.
- Exportar CSV, eliminar el registro de prueba con confirmación y cerrar sesión.
- Confirmar que no es posible descargar configuración, sesiones o archivos de datos desde URLs públicas.
- Confirmar almacenamiento efectivo en MySQL, no JSON. La aplicación rechaza JSON cuando `environment=production`.

## 8. Contenido y actualizaciones

Exportar `programs.json` desde el panel, reemplazar la copia del frontend, compilar y desplegar nuevamente. **No desplegar un seed antiguo sobre el contenido actual.** El instalador no sobrescribe un programa existente.

Una carga incremental no borra por sí sola páginas archivadas. Comparar con `deploy/manifest-publico.json` y retirar los HTML antiguos que ya no formen parte del nuevo build, sin borrar la API, el panel, la configuración ni el almacenamiento privado. Preparar respaldo y ventana de publicación antes de reemplazar contenido.

En actualizaciones, NO sobrescribir `config.local.php` ni `storage/` con carpetas vacías del paquete. Los respaldos deben protegerse fuera de la web y debe probarse su restauración.

## 9. Operación

Los leads quedan guardados en la base. **No hay correo saliente, SMTP, pago ni WhatsApp automático.** Los enlaces de WhatsApp abren una conversación que el visitante envía manualmente.

La tabla, métricas y exportación de esta base trabajan con las 5.000 consultas más recientes. Agregar paginación y procesamiento por lotes antes de superar ese volumen. El límite debe mantenerse visible al operador.

Opcionalmente, configurar una tarea cron diaria del hosting para limpiar archivos técnicos antiguos:

```bash
php /home/USUARIO/altetour-app/bin/cleanup.php
```

No elimina consultas. La política y automatización de retención de datos comerciales deben definirse con el responsable del negocio, incluyendo las copias de respaldo.
