// Versión en negativo: conserva los píxeles y contornos del logo original.
import fs from 'node:fs';
import {fileURLToPath} from 'node:url';
const original=new URL('../frontend/public/assets/logo.webp',import.meta.url);
const destination=new URL('../frontend/public/assets/logo-negative.svg',import.meta.url);
const image=fs.readFileSync(original).toString('base64');
// La luminancia del fondo blanco se vuelve transparente; la tinta azul, blanca.
// La silueta del avión y los espacios interiores permanecen como calados.
const svg=`<svg xmlns="http://www.w3.org/2000/svg" width="807" height="211" viewBox="0 0 807 211">
  <defs><filter id="negative" color-interpolation-filters="sRGB" x="0" y="0" width="100%" height="100%">
    <feColorMatrix type="matrix" values="0 0 0 0 1  0 0 0 0 1  0 0 0 0 1  -0.8504 -2.8608 -0.2888 0 3.6"/>
  </filter></defs>
  <image width="807" height="211" href="data:image/webp;base64,${image}" filter="url(#negative)"/>
</svg>\n`;
fs.writeFileSync(destination,svg);
console.log(fileURLToPath(destination));
