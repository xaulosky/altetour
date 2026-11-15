# Despliegue en el VPS (altetour.cl)

El sitio de producción corre en el VPS `207.244.224.159` (el mismo de controlapp.cl), con nginx, PHP 8.4-FPM y MySQL.

```text
/var/www/altetour/
  public_html/      páginas de Astro + admin/ y api/ (lo único servido por nginx)
  altetour-app/     src, bin, config (config.local.php, solo root y altetour) y storage (privado)
```

- nginx: `/etc/nginx/sites-available/altetour.cl` (copia en `altetour.cl.nginx`). `www` y HTTP redirigen a `https://altetour.cl`.
- PHP: pool propio `/etc/php/8.4/fpm/pool.d/altetour.conf`, usuario `altetour`, imágenes de hasta 6 MB.
- MySQL: base y usuario `altetour`; la clave solo está en `config.local.php` del servidor.
- Certificado: Let's Encrypt con `--webroot`, renovación automática de certbot.

## Actualizar el sitio

Desde la carpeta del proyecto, con `frontend/.env` en `SITE_URL=https://altetour.cl`:

```bash
cd frontend && npm run build && cd ..
node scripts/package-cpanel.mjs
tar -czf deploy.tgz -C deploy public_html altetour-app
scp deploy.tgz root@207.244.224.159:/tmp/altetour-deploy.tgz
scp scripts/vps/altetour-setup.sh root@207.244.224.159:/root/altetour-setup.sh
ssh root@207.244.224.159 'sed -i "s/\r$//" /root/altetour-setup.sh && bash /root/altetour-setup.sh && rm /tmp/altetour-deploy.tgz'
rm deploy.tgz
```

El script reemplaza `public_html` y el código, pero conserva `config.local.php`, `storage/` y la base de datos. No toca nginx.

## Noticias en producción

Las noticias se editan en `https://altetour.cl/admin/`, pero el sitio es estático: para que aparezcan hay que exportarlas y volver a compilar. En el servidor:

```bash
ssh root@207.244.224.159 'su -s /bin/bash altetour -c "php8.4 /var/www/altetour/altetour-app/bin/export-news.php"' > frontend/src/data/news.json
```

Las imágenes subidas (portadas y galerías) están en `/var/www/altetour/altetour-app/storage/news-images/` y hay que copiarlas a `frontend/public/assets/news/`. Luego se actualiza el sitio con los pasos de arriba.

Si preparas noticias fuera del panel (editando `frontend/src/data/news.json` y poniendo sus imágenes en `frontend/public/assets/news/`), después de desplegar cárgalas también en la base del servidor. Si no lo haces, la próxima exportación desde el panel las dejaría fuera del sitio:

```bash
scp frontend/src/data/news.json root@207.244.224.159:/tmp/news.json
ssh root@207.244.224.159 'chown altetour /tmp/news.json && su -s /bin/bash altetour -c "php8.4 /var/www/altetour/altetour-app/bin/import-news.php /tmp/news.json" && rm /tmp/news.json'
```

Solo agrega las que no existen; con `--replace` también reemplaza las existentes. Nunca borra.

Cada noticia puede tener una galería de hasta 12 fotos. El panel acepta hasta 5 MB por foto y 64 MB por envío (pool de PHP y `client_max_body_size` de nginx). Reduce cada foto a 1600 px en WebP y crea una miniatura de 640 px.

## Aviso por correo de consultas

Cada consulta nueva se guarda en el panel y además se avisa por correo a `notify.to` de `config.local.php` (hoy altetour@gmail.com). El correo sale de `avisos@altetour.cl` por el postfix del VPS, firmado con DKIM (selector `mail`, llave en `/etc/opendkim/keys/altetour.cl/`). Responder el aviso le escribe directo al cliente.

Para que Gmail no lo marque como spam, la zona DNS de altetour.cl (en Contabo) debe tener:

- `altetour.cl` TXT `v=spf1 ip4:207.244.224.159 ~all`
- `mail._domainkey.altetour.cl` TXT con la llave pública (`cat /etc/opendkim/keys/altetour.cl/mail.txt` en el VPS)
- `_dmarc.altetour.cl` TXT `v=DMARC1; p=none; adkim=r; aspf=r`

Si el correo falla, la consulta igual queda guardada; el error queda en el log de PHP del pool.

## Crear un administrador

```bash
ssh -t root@207.244.224.159 'su -s /bin/bash altetour -c "php8.4 /var/www/altetour/altetour-app/bin/create-admin.php correo@dominio.cl"'
```

Pide la contraseña (mínimo 12 caracteres) sin mostrarla.
