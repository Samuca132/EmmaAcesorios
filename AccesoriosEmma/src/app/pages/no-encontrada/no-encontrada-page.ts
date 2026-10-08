import { Location } from '@angular/common';
import { Component, inject } from '@angular/core';
import { MatButtonModule } from '@angular/material/button';
import { MatIconModule } from '@angular/material/icon';
import { Router, RouterLink } from '@angular/router';

/** Accesos rápidos a lo más usado, para no dejar a nadie sin salida. */
const ATAJOS = [
  { ruta: '/ventas', texto: 'Ventas', icono: 'point_of_sale' },
  { ruta: '/productos', texto: 'Productos', icono: 'inventory_2' },
  { ruta: '/clientes', texto: 'Clientes', icono: 'groups' },
  { ruta: '/reportes', texto: 'Reportes', icono: 'summarize' },
];

/** Error 404: cualquier dirección que no corresponde a una pantalla. */
@Component({
  selector: 'app-no-encontrada-page',
  imports: [RouterLink, MatButtonModule, MatIconModule],
  template: `
    <section class="page contenedor" aria-labelledby="titulo-404">
      <div class="ilustracion" aria-hidden="true">
        <span class="digito">4</span>
        <img src="logoEmma.png" alt="" width="132" height="132" />
        <span class="digito">4</span>
      </div>

      <h1 id="titulo-404">Esta página no existe</h1>
      <p class="text-muted">
        No encontramos <code>{{ direccion }}</code>. Puede que el enlace esté mal escrito o que la sección se haya movido.
      </p>

      <div class="botones">
        <a mat-flat-button routerLink="/inicio"><mat-icon>home</mat-icon>Ir al inicio</a>
        @if (puedeVolver) {
          <button mat-stroked-button type="button" (click)="volver()"><mat-icon>arrow_back</mat-icon>Volver</button>
        }
      </div>

      <nav class="atajos" aria-label="Secciones frecuentes">
        <span class="text-muted">O andá directo a:</span>
        @for (a of atajos; track a.ruta) {
          <a mat-button [routerLink]="a.ruta"><mat-icon>{{ a.icono }}</mat-icon>{{ a.texto }}</a>
        }
      </nav>
    </section>
  `,
  styles: `
    .contenedor {
      min-height: calc(100dvh - 160px);
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      text-align: center;
      gap: 8px;
    }
    .ilustracion { display: flex; align-items: center; gap: 4px; margin-bottom: 8px; }
    .digito {
      font: 700 clamp(5rem, 18vw, 9rem) / 1 var(--mat-sys-display-large-font, Roboto), sans-serif;
      color: var(--mat-sys-primary);
      letter-spacing: -0.04em;
    }
    .ilustracion img {
      width: clamp(84px, 16vw, 132px);
      height: auto;
      /* Cuadrado redondeado y no círculo: el texto del logo llega a los bordes de la imagen */
      border-radius: var(--mat-sys-corner-extra-large);
      object-fit: contain;
      /* El logo tiene letras oscuras: fondo claro también en modo oscuro */
      background: light-dark(var(--mat-sys-surface-container), #fbeef5);
      padding: 10px;
      box-shadow: 0 0 0 4px var(--mat-sys-primary-container);
      animation: balanceo 3s ease-in-out infinite;
    }
    h1 { font: var(--mat-sys-headline-medium); margin: 8px 0 0; }
    p { max-width: 520px; margin: 0 0 16px; }
    code {
      font-size: 0.9em;
      padding: 2px 6px;
      border-radius: var(--mat-sys-corner-small);
      background: var(--mat-sys-surface-container-high);
      word-break: break-all;
    }
    .botones { display: flex; gap: 12px; flex-wrap: wrap; justify-content: center; }
    .atajos {
      display: flex; flex-wrap: wrap; align-items: center; justify-content: center; gap: 4px;
      margin-top: 24px; padding-top: 16px; border-top: 1px solid var(--mat-sys-outline-variant);
    }
    .atajos > span { margin-right: 4px; }
    @keyframes balanceo {
      0%, 100% { transform: rotate(-6deg); }
      50% { transform: rotate(6deg); }
    }
    @media (prefers-reduced-motion: reduce) {
      .ilustracion img { animation: none; }
    }
  `,
})
export class NoEncontradaPage {
  private readonly location = inject(Location);
  readonly direccion = inject(Router).url;
  readonly atajos = ATAJOS;
  /** Si entró escribiendo la dirección o desde un marcador, "Volver" lo sacaría del sistema. */
  readonly puedeVolver = history.length > 1;

  volver(): void {
    this.location.back();
  }
}
