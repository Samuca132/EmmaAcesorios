// Convierte un .md de docs/ en un PDF con portada, índice paginado, encabezados,
// pie con número de página y diagramas Mermaid renderizados (Chromium + Paged.js).
//
// Dependencias (en una carpeta aparte, no forman parte del proyecto):
//   npm install marked@14.1.4 mermaid@11.4.1 pagedjs@0.4.3 playwright@1.49.1
// Uso (ejecutando desde esa carpeta; las rutas de entrada/salida, absolutas):
//   node docs/pdf/generar-pdf.mjs docs/MANUAL_USUARIO.md "docs/pdf/Emma Accesorios - Manual de usuario.pdf" manual
//   node docs/pdf/generar-pdf.mjs docs/DOCUMENTACION_TECNICA.md "docs/pdf/Emma Accesorios - Documentacion tecnica.pdf" tecnica
// CHROMIUM_PATH permite indicar el ejecutable de Chromium.
import fs from 'fs';
import os from 'os';
import path from 'path';
import { fileURLToPath, pathToFileURL } from 'url';
import { createRequire } from 'module';

const aqui = path.dirname(fileURLToPath(import.meta.url));
const [entrada, salida, tipo] = process.argv.slice(2);
const REPO = path.resolve(aqui, '../..');
// Las dependencias se buscan en el node_modules de la carpeta desde donde se ejecuta
const requerir = createRequire(path.join(process.cwd(), 'x.js'));
const { marked } = await import(pathToFileURL(requerir.resolve('marked')).href);
const { chromium } = requerir('playwright');

const META = {
  manual: {
    titulo: 'Manual de usuario',
    subtitulo: 'Guía de uso del sistema de gestión: ventas, compras, canjes, pases a venta, stock, clientes y reportes',
    audiencia: 'Personas que usan el sistema (usuarios y administradores)',
    corto: 'Manual de usuario',
  },
  tecnica: {
    titulo: 'Documentación técnica',
    subtitulo: 'Arquitectura, API, base de datos, reglas de negocio, seguridad, despliegue y hallazgos',
    audiencia: 'Desarrolladores y equipo técnico',
    corto: 'Documentación técnica',
  },
}[tipo];

let md = fs.readFileSync(entrada, 'utf8');
// La portada reemplaza al título; el índice se genera con números de página
md = md.replace(/^# .*\n/, '');
md = md.replace(/^## Índice\n[\s\S]*?\n---\n/m, '');
// Texto introductorio (antes del primer capítulo): va en la página del índice
const corte = md.search(/^## /m);
const intro = marked.parse(md.slice(0, corte)).replace(/href="#([^"]+)"/g, 'href="#s-$1"');
md = md.slice(corte);

const slug = (t) =>
  t.replace(/<[^>]+>/g, '').replace(/[*`]/g, '').trim().toLowerCase()
    .replace(/[^\p{L}\p{N}_\- ]/gu, '').replace(/ /g, '-');
const limpio = (t) => t.replace(/<[^>]+>/g, '').replace(/[*`]/g, '').replace(/\[([^\]]+)\]\([^)]+\)/g, '$1').trim();

const indice = [];
const renderer = new marked.Renderer();
renderer.heading = function ({ tokens, depth, text }) {
  const html = this.parser.parseInline(tokens);
  const id = 's-' + slug(text);
  if (depth === 2 || depth === 3) indice.push({ depth, id, texto: limpio(text) });
  return `<h${depth} id="${id}">${html}</h${depth}>\n`;
};
renderer.code = function ({ text, lang }) {
  const esc = text.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
  if (lang === 'mermaid') return `<div class="diagrama"><pre class="mermaid">${esc}</pre></div>\n`;
  return `<pre class="codigo"><code>${esc}</code></pre>\n`;
};
// Tablas envueltas para poder ajustar estilos
renderer.table = (function (orig) {
  return function (t) { return `<div class="tabla">${orig.call(this, t)}</div>`; };
})(renderer.table);

const cuerpo = marked.parse(md, { renderer, gfm: true }).replace(/href="#([^"]+)"/g, 'href="#s-$1"');
const logo = 'data:image/png;base64,' + fs.readFileSync(`${REPO}/AccesoriosEmma/public/logoEmma.png`).toString('base64');
const mermaidJs = fs.readFileSync(path.join(process.cwd(), 'node_modules/mermaid/dist/mermaid.min.js'), 'utf8');
const pagedJs = fs.readFileSync(path.join(process.cwd(), 'node_modules/pagedjs/dist/paged.polyfill.js'), 'utf8');

const tocHtml = indice.map((h) =>
  `<li class="n${h.depth}"><a href="#${h.id}"><span class="t">${h.texto}</span><span class="f"></span></a></li>`).join('\n');

const html = `<!doctype html>
<html lang="es"><head><meta charset="utf-8"><title>Emma Accesorios · ${META.titulo}</title>
<style>
:root { --marca: #a8216e; --marca-osc: #6e1347; --suave: #fbeef5; --borde: #e6cfdb; --texto: #2b2129; --gris: #6b5c66; }
@page {
  size: A4; margin: 22mm 18mm 20mm 18mm;
  @top-left { content: "Emma Accesorios"; font: 600 8pt Inter, sans-serif; color: var(--marca); }
  @top-right { content: string(capitulo); font: 8pt Inter, sans-serif; color: var(--gris); }
  @bottom-left { content: "${META.corto}"; font: 8pt Inter, sans-serif; color: var(--gris); }
  @bottom-right { content: "Página " counter(page) " de " counter(pages); font: 8pt Inter, sans-serif; color: var(--gris); }
}
@page portada { margin: 0; @top-left { content: none; } @top-right { content: none; } @bottom-left { content: none; } @bottom-right { content: none; } }
@page indice { @top-right { content: "Índice"; } }
html { font-family: Inter, "DejaVu Sans", sans-serif; font-size: 9.6pt; color: var(--texto); line-height: 1.5; }
body { margin: 0; }
.portada { page: portada; height: 297mm; width: 210mm; position: relative; overflow: hidden;
  background: linear-gradient(160deg, #fff 0%, #fff 52%, var(--suave) 52%, #f6d9e8 100%); }
.portada .banda { position: absolute; left: 0; top: 0; width: 14mm; height: 100%; background: linear-gradient(180deg, var(--marca), #e07b2a); }
.portada .logo { position: absolute; left: 32mm; top: 34mm; width: 46mm; height: 46mm; border-radius: 10mm; box-shadow: 0 0 0 2.5mm var(--suave); }
.portada .marca { position: absolute; left: 32mm; top: 92mm; font: 600 13pt Inter; color: var(--marca); letter-spacing: .08em; text-transform: uppercase; }
.portada h1 { position: absolute; left: 32mm; right: 20mm; top: 102mm; margin: 0; font: 800 34pt/1.1 "Inter Display", Inter; color: var(--texto); }
.portada .sub { position: absolute; left: 32mm; right: 30mm; top: 132mm; font: 400 13pt/1.45 Inter; color: var(--gris); }
.portada .ficha { position: absolute; left: 32mm; right: 30mm; bottom: 36mm; border-collapse: collapse; font-size: 9.5pt; }
.portada .ficha td { padding: 2.2mm 0; border-bottom: 0.3mm solid var(--borde); vertical-align: top; }
.portada .ficha td:first-child { width: 42mm; color: var(--marca); font-weight: 600; }
.portada .pie { position: absolute; left: 32mm; bottom: 18mm; font-size: 8pt; color: var(--gris); }
.indice { page: indice; break-after: page; }
.titulo-indice { font: 700 17pt/1.2 "Inter Display", Inter; color: var(--marca); border-bottom: 0.6mm solid var(--marca); padding-bottom: 2mm; margin: 0 0 5mm; }
.indice ul { list-style: none; padding: 0; margin: 0; }
.indice li a { display: flex; color: var(--texto); text-decoration: none; }
.indice li a { align-items: baseline; }
.indice li a .f { flex: 1; border-bottom: 0.3mm dotted #c9b3bf; margin: 0 1.5mm; }
.indice li a::after { content: target-counter(attr(href url), page); color: var(--gris); }
.indice .intro { margin-bottom: 6mm; }
.indice li.n2 { font-weight: 600; margin-top: 2.2mm; }
.indice li.n3 { padding-left: 7mm; font-size: 8.8pt; color: var(--gris); }
h2 { string-set: capitulo content(text); break-before: page; font: 700 17pt/1.2 "Inter Display", Inter; color: var(--marca);
  border-bottom: 0.6mm solid var(--marca); padding-bottom: 2mm; margin: 0 0 5mm; }
h3 { font: 700 12.5pt/1.25 Inter; color: var(--marca-osc); margin: 7mm 0 2.5mm; break-after: avoid; }
h4 { font: 700 10.5pt Inter; margin: 5mm 0 2mm; break-after: avoid; }
p { margin: 0 0 2.6mm; orphans: 3; widows: 3; }
a { color: var(--marca); text-decoration: none; }
ul, ol { margin: 0 0 3mm; padding-left: 6mm; }
li { margin: 0.6mm 0; }
code { font-family: "DejaVu Sans Mono", monospace; font-size: 8.2pt; background: #f6eef2; padding: 0.2mm 1mm; border-radius: 1mm; }
pre.codigo { background: #2b2129; color: #fbeef5; padding: 3.5mm 4mm; border-radius: 2mm; white-space: pre-wrap; word-break: break-word; font-size: 8pt; line-height: 1.45; break-inside: avoid; }
pre.codigo code { background: none; padding: 0; color: inherit; font-size: inherit; }
blockquote { margin: 0 0 3.5mm; padding: 2.5mm 4mm; background: var(--suave); border-left: 1.2mm solid var(--marca); border-radius: 0 2mm 2mm 0; break-inside: avoid; }
blockquote p:last-child { margin: 0; }
.tabla { margin: 0 0 4mm; }
table { border-collapse: collapse; width: 100%; font-size: ${tipo === 'tecnica' ? '7.6pt' : '8.4pt'}; }
th { background: var(--marca); color: #fff; text-align: left; font-weight: 600; padding: 1.6mm 2mm; }
td { padding: 1.4mm 2mm; border-bottom: 0.25mm solid var(--borde); vertical-align: top; hyphens: auto; }
td code, th code { font-size: 0.92em; word-break: break-all; padding: 0 0.6mm; }
td:first-child { min-width: 9mm; }
tr:nth-child(even) td { background: #fdf7fa; }
thead { display: table-header-group; }
tr { break-inside: avoid; }
hr { display: none; }
.diagrama { margin: 3mm 0 5mm; padding: 4mm; border: 0.3mm solid var(--borde); border-radius: 2.5mm; background: #fffafd; text-align: center; break-inside: avoid; }
.diagrama svg { height: auto; max-height: 215mm; }
.diagrama.girado { height: 232mm; position: relative; break-before: page; padding: 0; overflow: hidden; }
.diagrama.girado pre { margin: 0; }
.diagrama.girado svg { position: absolute; left: 4mm; top: 0; transform-origin: top left; transform: translateY(230mm) rotate(-90deg); max-height: none; }
input[type=checkbox] { margin-right: 1.5mm; }
</style></head><body>
<section class="portada">
  <div class="banda"></div>
  <img class="logo" src="${logo}">
  <div class="marca">Emma Accesorios</div>
  <h1>${META.titulo}</h1>
  <div class="sub">${META.subtitulo}</div>
  <table class="ficha">
    <tr><td>Sistema</td><td>Emma Accesorios · gestión comercial (frontend Angular 20 + API Symfony 3.4)</td></tr>
    <tr><td>Destinatarios</td><td>${META.audiencia}</td></tr>
    <tr><td>Versión analizada</td><td>Repositorio Samuca132/EmmaAcesorios · commit de7be73</td></tr>
    <tr><td>Fecha</td><td>8 de octubre de 2026</td></tr>
  </table>
  <div class="pie">Documento elaborado a partir del código fuente del sistema.</div>
</section>
<section class="indice"><div class="intro">${intro}</div><div class="titulo-indice">Índice</div><ul>${tocHtml}</ul></section>
<main>${cuerpo}</main>
<script>window.PagedConfig = { auto: false };</script>
<script>${pagedJs}</script>
<script>${mermaidJs}</script>
<script>
(async () => {
  mermaid.initialize({ startOnLoad: false, theme: 'base', securityLevel: 'loose',
    fontFamily: 'Inter, sans-serif',
    flowchart: { htmlLabels: false, useMaxWidth: true }, sequence: { useMaxWidth: true }, er: { useMaxWidth: true, fontSize: 15, minEntityWidth: 70, entityPadding: 10 },
    themeVariables: { primaryColor: '#fbeef5', primaryBorderColor: '#a8216e', primaryTextColor: '#2b2129',
      lineColor: '#7a1850', secondaryColor: '#fdebd9', tertiaryColor: '#ffffff', fontSize: '13px',
      actorBkg: '#fbeef5', actorBorder: '#a8216e', noteBkgColor: '#fdebd9' } });
  await mermaid.run({ querySelector: '.mermaid' });
  // Diagramas muy apaisados (p. ej. el ER): se giran 90° y ocupan una página
  document.querySelectorAll('.diagrama svg').forEach((svg) => {
    const vb = svg.viewBox.baseVal;
    if (vb && vb.width / vb.height > 2.6 && vb.width > 1200) {
      const caja = svg.closest('.diagrama');
      caja.classList.add('girado');
      svg.style.maxWidth = 'none';
      svg.style.width = '228mm';
      svg.style.height = (228 * vb.height / vb.width) + 'mm';
    }
  });
  await PagedPolyfill.preview();
  window.__listo = true;
})().catch(e => { window.__error = String(e && e.stack || e); });
</script>
</body></html>`;

const tmpHtml = path.join(os.tmpdir(), 'emma-doc-' + process.pid + '.html');
fs.writeFileSync(tmpHtml, html);

const browser = await chromium.launch(process.env.CHROMIUM_PATH ? { executablePath: process.env.CHROMIUM_PATH } : {});
const page = await browser.newPage();
page.on('pageerror', (e) => console.error('pageerror', e.message));
await page.goto('file://' + tmpHtml);
await page.waitForFunction(() => window.__listo || window.__error, null, { timeout: 180000 });
const err = await page.evaluate(() => window.__error);
if (err) { console.error(err); process.exit(1); }
await page.pdf({ path: salida, preferCSSPageSize: true, printBackground: true, outline: true, tagged: true });
const paginas = await page.evaluate(() => document.querySelectorAll('.pagedjs_page').length);
console.log(salida, paginas, 'páginas');
await browser.close();
fs.unlinkSync(tmpHtml);
