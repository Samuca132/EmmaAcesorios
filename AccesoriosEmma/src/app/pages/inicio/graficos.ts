import { CurrencyPipe, DecimalPipe } from '@angular/common';
import {
  Component, DestroyRef, ElementRef, effect, inject, signal, viewChild,
} from '@angular/core';
import { MatButtonToggleModule } from '@angular/material/button-toggle';
import { MatCardModule } from '@angular/material/card';
import { MatIconModule } from '@angular/material/icon';
import { MatProgressBarModule } from '@angular/material/progress-bar';
import type { Chart, ChartConfiguration, Plugin, TooltipItem } from 'chart.js';
import { ApiService } from '../../core/api.service';
import { Graficos } from '../../core/models';
import { TemaService } from '../../core/tema.service';

const moneda = new Intl.NumberFormat('es-AR', { style: 'currency', currency: 'ARS', maximumFractionDigits: 0 });
const monedaCorta = new Intl.NumberFormat('es-AR', {
  style: 'currency', currency: 'ARS', notation: 'compact', maximumFractionDigits: 1,
});
const MESES = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];

/** Colores del tema ya resueltos (light-dark() no se puede leer directo de una variable CSS). */
interface Paleta {
  acento: string;
  contexto: string;
  grilla: string;
  texto: string;
  fondo: string;
}

/**
 * Gráficos del panel de inicio:
 *  - ventas del mes acumuladas día a día contra el mes anterior (énfasis: el
 *    mes actual en el color de la marca, el anterior en gris de referencia);
 *  - los 10 productos más vendidos y las ventas por usuario (barras de una serie).
 *
 * Cada tarjeta se puede ver como tabla. Chart.js se carga recién acá, así no
 * agranda la carga inicial; los gráficos se redibujan al cambiar el tema.
 */
@Component({
  selector: 'app-graficos',
  imports: [MatCardModule, MatIconModule, MatButtonToggleModule, MatProgressBarModule, CurrencyPipe, DecimalPipe],
  template: `
    @if (cargando()) { <mat-progress-bar mode="indeterminate" /> }
    @if (datos(); as d) {
      <section class="graficos">
        <mat-card appearance="outlined" class="ancho">
          <mat-card-header>
            <mat-card-title>Ventas de {{ nombreMes(d.mes) }}</mat-card-title>
            <mat-card-subtitle>Acumulado día a día, comparado con {{ nombreMes(d.mesAnterior) }}</mat-card-subtitle>
            <mat-button-toggle-group class="vista" hideSingleSelectionIndicator [value]="vistaVentas()"
                                     (change)="vistaVentas.set($event.value)" aria-label="Ver como">
              <mat-button-toggle value="grafico" aria-label="Gráfico"><mat-icon>show_chart</mat-icon></mat-button-toggle>
              <mat-button-toggle value="tabla" aria-label="Tabla"><mat-icon>table_rows</mat-icon></mat-button-toggle>
            </mat-button-toggle-group>
          </mat-card-header>
          <mat-card-content>
            <div class="lienzo alto" [hidden]="vistaVentas() !== 'grafico'"><canvas #ventas role="img"
                 [attr.aria-label]="'Ventas acumuladas de ' + nombreMes(d.mes)"></canvas></div>
            @if (vistaVentas() === 'tabla') {
              <div class="tabla-scroll">
                <table>
                  <thead><tr><th>Día</th><th class="num">{{ nombreMes(d.mes) }}</th><th class="num">{{ nombreMes(d.mesAnterior) }}</th></tr></thead>
                  <tbody>
                    @for (f of d.ventasPorDia; track f.dia) {
                      <tr>
                        <td>{{ f.dia }}</td>
                        <td class="num">{{ f.actual === null ? '—' : (f.actual | currency: 'ARS' : 'symbol-narrow' : '1.0-0' : 'es-AR') }}</td>
                        <td class="num">{{ f.anterior === null ? '—' : (f.anterior | currency: 'ARS' : 'symbol-narrow' : '1.0-0' : 'es-AR') }}</td>
                      </tr>
                    }
                  </tbody>
                </table>
              </div>
            }
          </mat-card-content>
        </mat-card>

        <mat-card appearance="outlined">
          <mat-card-header>
            <mat-card-title>Más vendidos del mes</mat-card-title>
            <mat-card-subtitle>Unidades por producto</mat-card-subtitle>
            <mat-button-toggle-group class="vista" hideSingleSelectionIndicator [value]="vistaTop()"
                                     (change)="vistaTop.set($event.value)" aria-label="Ver como">
              <mat-button-toggle value="grafico" aria-label="Gráfico"><mat-icon>bar_chart</mat-icon></mat-button-toggle>
              <mat-button-toggle value="tabla" aria-label="Tabla"><mat-icon>table_rows</mat-icon></mat-button-toggle>
            </mat-button-toggle-group>
          </mat-card-header>
          <mat-card-content>
            @if (!d.masVendidos.length) {
              <p class="text-muted vacio">Todavía no hay ventas este mes.</p>
            }
            <div class="lienzo" [style.height.px]="altoBarras(d.masVendidos.length)"
                 [hidden]="vistaTop() !== 'grafico' || !d.masVendidos.length">
              <canvas #top role="img" aria-label="Productos más vendidos del mes"></canvas>
            </div>
            @if (vistaTop() === 'tabla' && d.masVendidos.length) {
              <table>
                <thead><tr><th>Producto</th><th class="num">Unidades</th><th class="num">Total</th><th class="num">Ganancia</th></tr></thead>
                <tbody>
                  @for (p of d.masVendidos; track p.nombre) {
                    <tr>
                      <td>{{ p.nombre }}</td>
                      <td class="num">{{ p.unidades | number: '1.0-0' : 'es-AR' }}</td>
                      <td class="num">{{ p.total | currency: 'ARS' : 'symbol-narrow' : '1.0-0' : 'es-AR' }}</td>
                      <td class="num">{{ p.ganancia | currency: 'ARS' : 'symbol-narrow' : '1.0-0' : 'es-AR' }}</td>
                    </tr>
                  }
                </tbody>
              </table>
            }
          </mat-card-content>
        </mat-card>

        <mat-card appearance="outlined">
          <mat-card-header>
            <mat-card-title>Ventas por usuario</mat-card-title>
            <mat-card-subtitle>Total vendido en el mes</mat-card-subtitle>
            <mat-button-toggle-group class="vista" hideSingleSelectionIndicator [value]="vistaUsuarios()"
                                     (change)="vistaUsuarios.set($event.value)" aria-label="Ver como">
              <mat-button-toggle value="grafico" aria-label="Gráfico"><mat-icon>bar_chart</mat-icon></mat-button-toggle>
              <mat-button-toggle value="tabla" aria-label="Tabla"><mat-icon>table_rows</mat-icon></mat-button-toggle>
            </mat-button-toggle-group>
          </mat-card-header>
          <mat-card-content>
            @if (!d.porUsuario.length) {
              <p class="text-muted vacio">Todavía no hay ventas este mes.</p>
            }
            <div class="lienzo" [style.height.px]="altoBarras(d.porUsuario.length)"
                 [hidden]="vistaUsuarios() !== 'grafico' || !d.porUsuario.length">
              <canvas #usuarios role="img" aria-label="Ventas por usuario en el mes"></canvas>
            </div>
            @if (vistaUsuarios() === 'tabla' && d.porUsuario.length) {
              <table>
                <thead><tr><th>Usuario</th><th class="num">Tickets</th><th class="num">Total</th></tr></thead>
                <tbody>
                  @for (u of d.porUsuario; track u.nombre) {
                    <tr>
                      <td>{{ u.nombre }}</td>
                      <td class="num">{{ u.tickets }}</td>
                      <td class="num">{{ u.total | currency: 'ARS' : 'symbol-narrow' : '1.0-0' : 'es-AR' }}</td>
                    </tr>
                  }
                </tbody>
              </table>
            }
          </mat-card-content>
        </mat-card>
      </section>
    }
    <!-- sonda para resolver los colores del tema (light-dark) -->
    <span #sonda class="sonda" aria-hidden="true"></span>
  `,
  styles: `
    :host { display: block; margin-bottom: 16px; }
    .graficos { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
    @media (max-width: 900px) { .graficos { grid-template-columns: 1fr; } }
    .ancho { grid-column: 1 / -1; }
    mat-card-header { position: relative; padding-right: 112px; }
    .vista { position: absolute; top: 12px; right: 12px; }
    .vista mat-icon { font-size: 20px; width: 20px; height: 20px; }
    .lienzo { position: relative; height: 220px; margin-top: 8px; }
    .lienzo.alto { height: 280px; }
    .tabla-scroll { max-height: 280px; overflow-y: auto; }
    table { width: 100%; border-collapse: collapse; font: var(--mat-sys-body-small); margin-top: 8px; }
    th { text-align: left; color: var(--mat-sys-on-surface-variant); font-weight: 500; }
    th, td { padding: 4px 8px; border-bottom: 1px solid var(--mat-sys-outline-variant); }
    .num { text-align: right; font-variant-numeric: tabular-nums; }
    .vacio { padding: 24px 0; text-align: center; }
    .sonda { position: absolute; width: 0; height: 0; overflow: hidden; }
  `,
})
export class GraficosPanel {
  private readonly api = inject(ApiService);
  private readonly tema = inject(TemaService);

  readonly datos = signal<Graficos | null>(null);
  readonly cargando = signal(true);
  readonly vistaVentas = signal<'grafico' | 'tabla'>('grafico');
  readonly vistaTop = signal<'grafico' | 'tabla'>('grafico');
  readonly vistaUsuarios = signal<'grafico' | 'tabla'>('grafico');

  private readonly lienzoVentas = viewChild<ElementRef<HTMLCanvasElement>>('ventas');
  private readonly lienzoTop = viewChild<ElementRef<HTMLCanvasElement>>('top');
  private readonly lienzoUsuarios = viewChild<ElementRef<HTMLCanvasElement>>('usuarios');
  private readonly sonda = viewChild.required<ElementRef<HTMLElement>>('sonda');

  private graficos: Chart[] = [];
  private chartJs?: typeof import('chart.js');

  constructor() {
    this.api.graficos().subscribe({
      next: (d) => {
        this.datos.set(d);
        this.cargando.set(false);
      },
      // Los gráficos son un extra del panel: si fallan, el resto sigue andando
      error: () => this.cargando.set(false),
    });

    // Dibuja cuando hay datos y vuelve a dibujar si cambia el tema
    effect(() => {
      const datos = this.datos();
      this.tema.oscuro();
      if (datos) {
        // esperar a que el DOM tenga los <canvas> y el tema nuevo aplicado
        requestAnimationFrame(() => void this.dibujar(datos));
      }
    });

    inject(DestroyRef).onDestroy(() => this.destruir());
  }

  nombreMes(aaaamm: string): string {
    return MESES[Number(aaaamm.slice(5, 7)) - 1] ?? aaaamm;
  }

  /** Alto según la cantidad de barras: bandas de 32px (barra de 20px + aire). */
  altoBarras(n: number): number {
    return Math.max(80, n * 32 + 24);
  }

  private async dibujar(d: Graficos): Promise<void> {
    this.chartJs ??= await import('chart.js/auto');
    const { Chart } = this.chartJs;
    const c = this.paleta();
    this.destruir();

    Chart.defaults.font.family = 'Roboto, sans-serif';
    Chart.defaults.color = c.texto;

    const ventas = this.lienzoVentas()?.nativeElement;
    if (ventas) this.graficos.push(new Chart(ventas, this.configVentas(d, c)));
    const top = this.lienzoTop()?.nativeElement;
    if (top && d.masVendidos.length) {
      this.graficos.push(new Chart(top, this.configBarras(
        d.masVendidos.map((p) => p.nombre), d.masVendidos.map((p) => p.unidades), c,
        (v) => `${v} u.`,
        (i) => [`${d.masVendidos[i].unidades} unidades`, `Total ${moneda.format(d.masVendidos[i].total)}`, `Ganancia ${moneda.format(d.masVendidos[i].ganancia)}`],
      )));
    }
    const usuarios = this.lienzoUsuarios()?.nativeElement;
    if (usuarios && d.porUsuario.length) {
      this.graficos.push(new Chart(usuarios, this.configBarras(
        d.porUsuario.map((u) => u.nombre), d.porUsuario.map((u) => u.total), c,
        (v) => monedaCorta.format(v),
        (i) => [moneda.format(d.porUsuario[i].total), `${d.porUsuario[i].tickets} tickets`],
      )));
    }
  }

  private configVentas(d: Graficos, c: Paleta): ChartConfiguration<'line'> {
    const linea = (color: string) => ({
      borderColor: color,
      backgroundColor: color,
      borderWidth: 2,
      borderCapStyle: 'round' as const,
      borderJoinStyle: 'round' as const,
      pointRadius: 0,
      pointHoverRadius: 5,
      pointHoverBorderWidth: 2,
      pointHoverBorderColor: c.fondo, // anillo del color de fondo
      tension: 0.2,
    });
    return {
      type: 'line',
      data: {
        labels: d.ventasPorDia.map((f) => f.dia),
        datasets: [
          { label: this.capitalizar(this.nombreMes(d.mes)), data: d.ventasPorDia.map((f) => f.actual), ...linea(c.acento) },
          { label: this.capitalizar(this.nombreMes(d.mesAnterior)), data: d.ventasPorDia.map((f) => f.anterior), ...linea(c.contexto) },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        animation: false,
        interaction: { mode: 'index', intersect: false },
        layout: { padding: { right: 72 } }, // lugar para las etiquetas al final de cada línea
        scales: {
          x: {
            grid: { display: false },
            border: { color: c.grilla },
            ticks: { color: c.texto, maxRotation: 0, autoSkip: true, autoSkipPadding: 12 },
            title: { display: true, text: 'Día del mes', color: c.texto },
          },
          y: {
            beginAtZero: true,
            grid: { color: c.grilla, lineWidth: 1 },
            border: { display: false },
            ticks: { color: c.texto, maxTicksLimit: 5, callback: (v) => monedaCorta.format(Number(v)) },
          },
        },
        plugins: {
          legend: {
            position: 'top',
            align: 'start',
            labels: { color: c.texto, usePointStyle: true, pointStyle: 'line', boxWidth: 24 },
          },
          tooltip: this.tooltip(c, {
            title: (items: TooltipItem<'line'>[]) => `Día ${items[0]?.label}`,
            label: (item: TooltipItem<'line'>) => ` ${item.dataset.label}: ${item.parsed.y === null ? '—' : moneda.format(item.parsed.y)}`,
          }),
        },
      },
      plugins: [crucero(c.grilla), etiquetasFinales(c.texto)],
    };
  }

  private configBarras(
    etiquetas: string[], valores: number[], c: Paleta,
    formato: (v: number) => string, detalle: (i: number) => string[],
  ): ChartConfiguration<'bar'> {
    return {
      type: 'bar',
      data: {
        labels: etiquetas,
        datasets: [{
          data: valores,
          backgroundColor: c.acento,
          hoverBackgroundColor: c.acento + 'cc',
          maxBarThickness: 20,
          borderRadius: 4,
          borderSkipped: 'start', // redondeado solo en la punta, recto en la base
        }],
      },
      options: {
        indexAxis: 'y',
        responsive: true,
        maintainAspectRatio: false,
        animation: false,
        layout: { padding: { right: 56 } }, // lugar para el valor en la punta
        scales: {
          x: { display: false, beginAtZero: true, grid: { display: false } },
          y: { grid: { display: false }, border: { display: false }, ticks: { color: c.texto, autoSkip: false } },
        },
        plugins: {
          legend: { display: false }, // una sola serie: el título de la tarjeta ya la nombra
          tooltip: this.tooltip(c, {
            title: (items: TooltipItem<'bar'>[]) => items[0]?.label ?? '',
            label: (item: TooltipItem<'bar'>) => detalle(item.dataIndex),
          }),
        },
      },
      plugins: [valoresEnLaPunta(c.texto, formato)],
    };
  }

  private tooltip(c: Paleta, callbacks: object) {
    return {
      backgroundColor: c.fondo,
      titleColor: c.texto,
      bodyColor: c.texto,
      borderColor: c.grilla,
      borderWidth: 1,
      padding: 10,
      boxPadding: 4,
      usePointStyle: true,
      callbacks,
    };
  }

  /** Lee los colores del tema actual a través de un elemento sonda. */
  private paleta(): Paleta {
    const sonda = this.sonda().nativeElement;
    const leer = (variable: string) => {
      sonda.style.color = `var(${variable})`;
      return aHex(getComputedStyle(sonda).color);
    };
    return {
      acento: leer('--grafico-acento'),
      contexto: leer('--grafico-contexto'),
      grilla: leer('--grafico-grilla'),
      texto: leer('--grafico-texto'),
      fondo: leer('--grafico-fondo'),
    };
  }

  private capitalizar(texto: string): string {
    return texto.charAt(0).toUpperCase() + texto.slice(1);
  }

  private destruir(): void {
    this.graficos.forEach((g) => g.destroy());
    this.graficos = [];
  }
}

/** "rgb(169, 0, 169)" → "#a900a9" (para poder agregarle transparencia). */
function aHex(rgb: string): string {
  const m = rgb.match(/\d+(\.\d+)?/g);
  if (!m || m.length < 3) return rgb;
  return '#' + m.slice(0, 3).map((n) => Math.round(Number(n)).toString(16).padStart(2, '0')).join('');
}

/** Línea vertical que sigue al cursor en el día más cercano. */
function crucero(color: string): Plugin<'line'> {
  return {
    id: 'crucero',
    afterDatasetsDraw(chart) {
      const activo = chart.tooltip?.getActiveElements()[0];
      if (!activo) return;
      const { ctx, chartArea } = chart;
      ctx.save();
      ctx.strokeStyle = color;
      ctx.lineWidth = 1;
      ctx.beginPath();
      ctx.moveTo(activo.element.x, chartArea.top);
      ctx.lineTo(activo.element.x, chartArea.bottom);
      ctx.stroke();
      ctx.restore();
    },
  };
}

/** Valor al final de cada línea (último día con dato), en color de texto. */
function etiquetasFinales(color: string): Plugin<'line'> {
  return {
    id: 'etiquetasFinales',
    afterDatasetsDraw(chart) {
      const { ctx } = chart;
      ctx.save();
      ctx.fillStyle = color;
      ctx.font = '500 12px Roboto, sans-serif';
      ctx.textBaseline = 'middle';
      const usados: { x: number; y: number }[] = [];
      chart.data.datasets.forEach((ds, i) => {
        const datos = ds.data as (number | null)[];
        const ultimo = datos.reduce<number>((u, v, j) => (v !== null ? j : u), -1);
        if (ultimo < 0) return;
        const punto = chart.getDatasetMeta(i).data[ultimo];
        // si dos etiquetas chocan, se omite la segunda (la leyenda y el tooltip la cubren)
        if (usados.some((u) => Math.abs(u.y - punto.y) < 14 && Math.abs(u.x - punto.x) < 70)) return;
        usados.push({ x: punto.x, y: punto.y });
        ctx.fillText(monedaCorta.format(datos[ultimo]!), punto.x + 8, punto.y);
      });
      ctx.restore();
    },
  };
}

/** Valor en la punta de cada barra horizontal. */
function valoresEnLaPunta(color: string, formato: (v: number) => string): Plugin<'bar'> {
  return {
    id: 'valoresEnLaPunta',
    afterDatasetsDraw(chart) {
      const { ctx } = chart;
      ctx.save();
      ctx.fillStyle = color;
      ctx.font = '12px Roboto, sans-serif';
      ctx.textBaseline = 'middle';
      chart.getDatasetMeta(0).data.forEach((barra, i) => {
        ctx.fillText(formato(chart.data.datasets[0].data[i] as number), barra.x + 6, barra.y);
      });
      ctx.restore();
    },
  };
}
