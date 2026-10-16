# Imágenes del sitio

Se crearon doce imágenes ilustrativas mediante la herramienta integrada `image_gen`, con estilo fotográfico, detalle natural y una paleta de azules y tonos cálidos. No representan fotografías tomadas por la agencia ni servicios contratados. El logo y el afiche original se conservan.

| Archivo en `frontend/public/assets/` | Uso |
| --- | --- |
| `patagonia.webp` | Portada de inicio y vista previa social |
| `galata.webp` | Tarjeta del programa de Turquía, en inicio y catálogo |
| `biobio.webp` | Relato de origen de la agencia en inicio |
| `estambul.webp` | Cabecera del programa de Turquía |
| `capadocia.webp` | Galería de Turquía |
| `pamukkale.webp` | Galería de Turquía |
| `izmir.webp` | Galería del Egeo |
| `misiones.webp` | Misiones comerciales y tarjetas de servicios |
| `atacama.webp` | Cabecera de turismo receptivo |
| `planificacion.webp` | Cabecera de contacto |
| `viajeros.webp` | Cabecera de viajes y tarjetas de servicios |
| `valparaiso.webp` | Tarjetas de turismo receptivo y galería de Chile |

No se repiten imágenes de destino dentro de una misma página. La tarjeta del mismo programa conserva su imagen entre inicio y catálogo.

Los originales de 1536 × 1024 se guardan en `design/generated-images/`. Las versiones WebP tienen el mismo tamaño y variantes de 960 y 640 píxeles; el navegador elige mediante `srcset`. Los prompts completos y la procedencia están en `scripts/generated-images.json` y `scripts/generated-images-extra.json`. Todas se crearon con la herramienta integrada `image_gen`. `python scripts/prepare-generated-images.py` permite volver a codificar las imágenes desde los originales locales.

Las escenas solicitadas fueron: Bósforo al atardecer, Capadocia al amanecer con globos, terrazas de Pamukkale, bahía de la costa turca del Egeo, Torre de Gálata, cascadas de Biobío y montañas con lago de Patagonia. En todas se solicitaron detalle fotográfico, color natural y ausencia de texto, marcas y montajes.

## Logo en negativo

`frontend/public/assets/logo-negative.svg` usa el logo original incrustado y un filtro de luminancia: convierte la tinta a blanco y el fondo, el avión y los espacios interiores a transparencia, sin redibujar la marca. Se genera con `node scripts/build-negative-logo.mjs`. Se utiliza en el navbar sobre el hero y en el footer oscuro; las páginas con cabecera clara conservan el logo a color. Los intentos de conversión con ImageGen se descartaron por artefactos y no forman parte del sitio.
