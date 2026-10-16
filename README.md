# ALTE TOUR · Primera versión del sitio

Sitio corporativo y comercial basado en el dossier de Alte Tour y el programa **Turquía Imbatible 2027** entregados por Pablo. Identidad visual: logo original extraído del Word, azul profundo, tonos arena, acentos dorados y imágenes ilustrativas de destinos generadas con IA en alta resolución. Se mantiene el afiche original del programa. No se han agregado testimonios, oficinas, certificaciones ni programas ficticios.

## Qué contiene esta entrega

- Frontend preparado para **Astro estático**, con nueve rutas, diseño adaptable a móvil, buscador de programas, itinerario desplegable, tarifas, preguntas y formulario.
- Vista previa **autocontenida** en `altetour-vista-previa.html`: abre el archivo en un navegador. Navegación simulada entre las mismas páginas; no guarda ni envía datos a un servidor. Puede preparar un mensaje para WhatsApp, que el visitante debe enviar explícitamente.
- Backend **PHP nativo**, sin Laravel ni Composer en esta versión. API de consultas, validación en servidor, sesiones, CSRF, límites de frecuencia, idempotencia y panel autenticado.
- Adaptador **MySQL/PDO para producción** y adaptador JSON privado con bloqueo de archivos, exclusivamente para pruebas/desarrollo.
- Panel: consultar solicitudes, cambiar estado, exportar CSV, eliminar registros con confirmación y editar título, descripción, tarifas y estado del programa cargado.
- Scripts para exportar contenido y preparar la estructura de cPanel.

## Estado de verificación

La vista previa comparte los renderizadores, contenido, CSS y JavaScript con las páginas de Astro. Fue revisada en Chromium, en escritorio y móvil. La sintaxis PHP y los flujos de consulta/autenticación fueron probados localmente con PHP 8.4 y el adaptador JSON.

**Compilación de Astro verificada localmente el 12 de octubre de 2026**, después de instalar las dependencias y renovar las imágenes. El proyecto incluye `package-lock.json`. Tampoco se probó el adaptador MySQL en un servidor real porque el entorno no dispone del controlador PDO MySQL. El repositorio se publica en GitHub con una versión estática para GitHub Pages; no se ha desplegado el backend PHP en un hosting de producción.

Ver el alcance y resultados exactos en `docs/QA.md`.

## Inicio rapido en Windows

Haz doble clic en **`iniciar-sitio.bat`** desde la carpeta del proyecto. Requiere Node.js 22.12 o superior, npm y PHP 8.3 o superior disponibles en `PATH`.

El lanzador instala las dependencias si faltan, crea `frontend/.env` si no existe, prepara el almacenamiento de desarrollo sin reemplazar programas existentes e inicia PHP y Astro. Abre el navegador cuando el sitio y la API estan disponibles. Conserva las configuraciones locales existentes.

La web usa `http://127.0.0.1:4321/` o el primer puerto libre hasta 4340. El panel queda en `/admin/` de la misma URL. PHP usa el puerto 8766; si esta ocupado, el lanzador lo informa sin detener procesos ajenos.

Deja abierta la ventana y pulsa **Ctrl+C** para detener los dos servidores iniciados por el lanzador.

El panel no tiene credenciales por defecto. Para crear un administrador, usa `php backend/bin/create-admin.php tu-correo@dominio.cl` desde la carpeta del proyecto; solicita una contrasena de al menos 12 caracteres.

Opciones desde una terminal:

```bat
iniciar-sitio.bat --check
iniciar-sitio.bat --no-open
```

`--check` solo comprueba los requisitos; `--no-open` inicia el sitio sin abrir el navegador. `--smoke-test` verifica el arranque real y detiene los servidores al terminar, sin abrir el navegador.

## Desarrollo local

Requisitos elegidos: Node.js 22.12 o superior, PHP 8.3 o superior y, para producción, PDO MySQL habilitado. No se necesita Node ejecutándose en cPanel: se utiliza para compilar fuera del hosting.

Desde la carpeta del proyecto:

```bash
php backend/bin/install.php --demo
php backend/bin/create-admin.php tu-correo@dominio.cl
php -S 127.0.0.1:8766 -t backend/public
```

El comando de administrador solicita la contraseña por entrada estándar. No existe un usuario ni una contraseña por defecto. La cuenta que se utilice en pruebas debe ser distinta a la de producción.

En otra terminal:

```bash
cd frontend
npm install
cp .env.example .env
npm run dev
```

En Windows, copia `.env.example` a `.env` con el explorador o PowerShell. Astro redirige `/api` y `/admin` al servidor PHP local del puerto 8766. El panel queda en `/admin/` a través del mismo servidor de desarrollo.

También puedes generar una vista sin instalar npm:

```bash
node scripts/build-preview.mjs
```

Este último comando genera una demostración usando el renderizador compartido, **no compila Astro**. Si sirves `preview/` con un servidor web, su formulario intenta comunicarse con la API local; si la API no existe, muestra un error real y ofrece WhatsApp. El HTML autocontenido permanece siempre en modo demostración.

## Arquitectura

```text
frontend/
  src/data/         Contenido corporativo y programa en JSON
  src/lib/          Renderizadores compartidos; salida escapada
  src/layouts/      Layout Astro y metadatos
  src/pages/        Rutas estáticas, robots y 404
  src/scripts/      Menú, filtros, itinerario y formulario
  src/styles/       Diseño responsive
  public/assets/    Recursos locales del material entregado
backend/
  src/              Validación, sesión, persistencia
  public/api/       API HTTP de consultas
  public/admin/     Panel PHP autenticado
  config/           Configuración privada, SQL y semilla del programa
  storage/          Datos de desarrollo, sesiones y límites; siempre privado
  bin/              Instalación, administrador, exportación y limpieza
scripts/            Vista previa y empaquetado cPanel
preview/            Render estático de demostración
```

## Cómo se actualiza el contenido

**Guardar en el panel no cambia automáticamente el HTML estático.** El flujo de esta versión es:

1. Editar el programa en el panel y guardar.
2. Exportar `programs.json` desde el panel o con `php backend/bin/export-programs.php`.
3. Reemplazar `frontend/src/data/programs.json` por la exportación.
4. Compilar Astro y publicar el nuevo `dist/`.

El formulario y los estados de consultas son dinámicos y se registran inmediatamente en el backend. El estado publicado/borrador también se valida en el backend al recibir una consulta. Un programa archivado puede seguir viéndose en un HTML antiguo hasta volver a publicar: no omitir el paso de despliegue.

La pantalla actual edita el programa cargado, no es todavía un CMS genérico para crear cualquier viaje. Fechas, rutas, vuelos, textos específicos, documentos, preguntas y días se mantienen en el contenido y el renderizador. Antes de agregar otro destino hay que convertir los bloques específicos de Turquía en campos genéricos. Ver `docs/SIGUIENTE-ETAPA.md`.

## Publicación

Configurar el dominio real en `frontend/.env` como `SITE_URL`. Mantener `PUBLIC_SITE_INDEXABLE=false` hasta aprobar la información comercial, privacidad y pruebas del hosting. Al autorizar indexación, cambiarlo a `true` y recompilar.

```bash
cd frontend
npm install
npm run build
cd ..
node scripts/package-cpanel.mjs
```

La guía de instalación está en `docs/CPANEL.md`. El empaquetador se niega a crear una distribución de Astro si no existe `frontend/dist/index.html`. El modo explícito `--preview` crea únicamente un paquete de ensayo basado en el render compartido.

## Antes del lanzamiento

Revisar **`docs/CONTENIDO-Y-PENDIENTES.md`** con Alte Tour. La web recibe consultas, no cobra ni confirma cupos. No se prometen vuelos sin escalas, hoteles específicos, cupos limitados, reembolsos, salida garantizada, paseo en globo incluido ni coberturas de seguro no confirmadas.

Las doce imágenes editoriales actuales son ilustraciones generadas con IA, de 1536 × 1024 píxeles, optimizadas como WebP con variantes para móvil. El logo y el afiche original se conservan. Ver `docs/IMAGENES.md` para usos, originales y prompts. No se han utilizado fotografías web ni tipografías distribuidas como archivos.

## GitHub y GitHub Pages

- Repositorio: https://github.com/xaulosky/altetour
- Vista pública estática: https://xaulosky.github.io/altetour/

Cada push a `main` ejecuta `.github/workflows/pages.yml`, genera el sitio con `node scripts/build-pages.mjs` y lo publica en Pages. Comparte contenido, renderizadores, CSS y comportamiento con el sitio local. No necesita PHP ni instalar dependencias npm para generar esta variante.

El prefijo `/altetour/` se aplica a navegación, recursos y variantes de imágenes. El formulario prepara una consulta que el visitante debe enviar explícitamente por WhatsApp. No hay API ni panel administrativo publicados en Pages. Los datos del catálogo se leen de los JSON del proyecto; para cambiarlos hay que actualizar esos archivos y subir un nuevo commit.

Esta vista conserva `noindex,nofollow` como versión de revisión. La configuración de indexación y dominio definitivo del sitio de producción sigue pendiente. No se publican configuraciones privadas, bases locales, consultas, sesiones ni archivos `.env`.

El HTML portable y `preview/` se regeneran con `node scripts/build-preview.mjs` y se excluyen de Git por ser archivos derivados. Los originales y prompts de las imágenes están incluidos para mantener el proyecto editable.
