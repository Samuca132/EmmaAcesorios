import { CurrencyPipe, DecimalPipe } from '@angular/common';
import { Component, OnInit, computed, inject, signal } from '@angular/core';
import { takeUntilDestroyed, toSignal } from '@angular/core/rxjs-interop';
import { FormControl, FormGroup, ReactiveFormsModule } from '@angular/forms';
import { MatButtonModule } from '@angular/material/button';
import { MatCardModule } from '@angular/material/card';
import { MatExpansionModule } from '@angular/material/expansion';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatIconModule } from '@angular/material/icon';
import { MatInputModule } from '@angular/material/input';
import { MatProgressSpinnerModule } from '@angular/material/progress-spinner';
import { MatSelectModule } from '@angular/material/select';
import { MatTableModule } from '@angular/material/table';
import { MatTabsModule } from '@angular/material/tabs';
import { debounceTime } from 'rxjs';
import { ApiService, sinError } from '../../core/api.service';
import {
  Ciudad, Cliente, Insumo, Producto, Proveedor, Reporte, TipoDato, TipoReporte, UsuarioResumen,
} from '../../core/models';
import { NotificacionService, mensajeDeError } from '../../core/notificacion.service';
import { Columna, DataTable, TipoColumna } from '../../shared/data-table';
import { PageHeader } from '../../shared/page-header';

type Fila = Record<string, string | number | null>;

/** Filtros específicos que muestra cada reporte (además de fechas y usuario). */
const FILTROS: Record<TipoReporte, ('clienteId' | 'productoId' | 'ciudadId' | 'proveedorId' | 'insumoId')[]> = {
  ventas: ['clienteId', 'productoId', 'ciudadId'],
  compras: ['proveedorId', 'insumoId'],
  canjes: ['proveedorId', 'productoId', 'insumoId'],
};

const TIPOS_TABLA: Record<TipoDato, TipoColumna> = {
  texto: 'texto', fecha: 'fecha', fechaHora: 'fechaHora', entero: 'numero', moneda: 'moneda',
};

function hoy(): string {
  const d = new Date();
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
}

/**
 * Reportes de ventas, compras y canjes con filtros, vista previa y
 * descarga en Excel (el .xlsx lo genera el backend).
 */
@Component({
  selector: 'app-reportes-page',
  imports: [
    PageHeader, DataTable, ReactiveFormsModule, MatTabsModule, MatFormFieldModule, MatInputModule,
    MatSelectModule, MatButtonModule, MatIconModule, MatCardModule, MatTableModule, MatExpansionModule,
    MatProgressSpinnerModule, CurrencyPipe, DecimalPipe,
  ],
  template: `
    <div class="page">
      <app-page-header titulo="Reportes" subtitulo="Elegí el reporte y los filtros. Lo que ves abajo es lo mismo que se descarga en Excel.">
        <button mat-flat-button (click)="descargar()" [disabled]="descargando() || !reporte()">
          @if (descargando()) { <mat-spinner diameter="18" /> } @else { <mat-icon>download</mat-icon> }
          Descargar Excel
        </button>
      </app-page-header>

      <mat-tab-group [selectedIndex]="indice()" (selectedIndexChange)="cambiarTipo($event)" mat-stretch-tabs="false">
        <mat-tab><ng-template mat-tab-label><mat-icon class="tab-icono">point_of_sale</mat-icon>Ventas</ng-template></mat-tab>
        <mat-tab><ng-template mat-tab-label><mat-icon class="tab-icono">shopping_cart</mat-icon>Compras</ng-template></mat-tab>
        <mat-tab><ng-template mat-tab-label><mat-icon class="tab-icono">swap_horiz</mat-icon>Canjes</ng-template></mat-tab>
      </mat-tab-group>

      <form class="filtros" [formGroup]="filtros">
        <mat-form-field subscriptSizing="dynamic">
          <mat-label>Desde</mat-label>
          <input matInput type="date" formControlName="desde" />
        </mat-form-field>
        <mat-form-field subscriptSizing="dynamic">
          <mat-label>Hasta</mat-label>
          <input matInput type="date" formControlName="hasta" />
        </mat-form-field>
        @if (muestra('clienteId')) {
          <mat-form-field subscriptSizing="dynamic">
            <mat-label>Cliente</mat-label>
            <mat-select formControlName="clienteId">
              <mat-option [value]="null">Todos</mat-option>
              @for (c of clientes(); track c.id) { <mat-option [value]="c.id">{{ c.nombre }}</mat-option> }
            </mat-select>
          </mat-form-field>
        }
        @if (muestra('proveedorId')) {
          <mat-form-field subscriptSizing="dynamic">
            <mat-label>Proveedor</mat-label>
            <mat-select formControlName="proveedorId">
              <mat-option [value]="null">Todos</mat-option>
              @for (p of proveedores(); track p.id) { <mat-option [value]="p.id">{{ p.nombre }}</mat-option> }
            </mat-select>
          </mat-form-field>
        }
        @if (muestra('productoId')) {
          <mat-form-field subscriptSizing="dynamic">
            <mat-label>Producto</mat-label>
            <mat-select formControlName="productoId">
              <mat-option [value]="null">Todos</mat-option>
              @for (p of productos(); track p.id) { <mat-option [value]="p.id">{{ p.nombre }}</mat-option> }
            </mat-select>
          </mat-form-field>
        }
        @if (muestra('insumoId')) {
          <mat-form-field subscriptSizing="dynamic">
            <mat-label>Insumo</mat-label>
            <mat-select formControlName="insumoId">
              <mat-option [value]="null">Todos</mat-option>
              @for (i of insumos(); track i.id) { <mat-option [value]="i.id">{{ i.nombre }}</mat-option> }
            </mat-select>
          </mat-form-field>
        }
        @if (muestra('ciudadId')) {
          <mat-form-field subscriptSizing="dynamic">
            <mat-label>Ciudad</mat-label>
            <mat-select formControlName="ciudadId">
              <mat-option [value]="null">Todas</mat-option>
              @for (c of ciudades(); track c.id) { <mat-option [value]="c.id">{{ c.nombre }}</mat-option> }
            </mat-select>
          </mat-form-field>
        }
        <mat-form-field subscriptSizing="dynamic">
          <mat-label>Registró</mat-label>
          <mat-select formControlName="usuarioId">
            <mat-option [value]="null">Todos</mat-option>
            @for (u of usuarios(); track u.id) { <mat-option [value]="u.id">{{ u.nombre }}</mat-option> }
          </mat-select>
        </mat-form-field>
        <div class="atajos">
          <button mat-button type="button" (click)="periodo('mes')">Este mes</button>
          <button mat-button type="button" (click)="periodo('anterior')">Mes anterior</button>
          <button mat-button type="button" (click)="periodo('anio')">Este año</button>
          <button mat-button type="button" (click)="periodo('todo')"><mat-icon>filter_alt_off</mat-icon>Sin filtros</button>
        </div>
      </form>

      @if (error()) {
        <p class="error" role="alert"><mat-icon>error</mat-icon>{{ error() }}</p>
      }

      @if (reporte(); as r) {
        <p class="text-muted filtros-aplicados"><mat-icon>filter_alt</mat-icon>{{ r.filtros }}</p>

        <section class="kpis">
          @for (t of r.totales; track t.titulo) {
            <mat-card appearance="outlined">
              <mat-card-content>
                <span class="label">{{ t.titulo }}</span>
                <span class="valor" [class.negativo]="t.valor < 0">
                  @if (t.tipo === 'moneda') {
                    {{ t.valor | currency: 'ARS' : 'symbol-narrow' : '1.0-2' : 'es-AR' }}
                  } @else {
                    {{ t.valor | number: '1.0-0' : 'es-AR' }}
                  }
                </span>
              </mat-card-content>
            </mat-card>
          }
        </section>

        <mat-accordion multi class="resumen">
          @for (tabla of r.resumen; track tabla.titulo) {
            <mat-expansion-panel>
              <mat-expansion-panel-header>
                <mat-panel-title>{{ tabla.titulo }}</mat-panel-title>
                <mat-panel-description>{{ tabla.filas.length }} {{ tabla.filas.length === 1 ? 'grupo' : 'grupos' }}</mat-panel-description>
              </mat-expansion-panel-header>
              <div class="scroll">
                <table mat-table [dataSource]="tabla.filas" class="tabla-resumen">
                  @for (col of tabla.columnas; track col.clave) {
                    <ng-container [matColumnDef]="col.clave">
                      <th mat-header-cell *matHeaderCellDef [class.num]="col.tipo !== 'texto'">{{ col.titulo }}</th>
                      <td mat-cell *matCellDef="let f" [class.num]="col.tipo !== 'texto'" [class.negativo]="col.tipo === 'moneda' && f[col.clave] < 0">
                        @switch (col.tipo) {
                          @case ('moneda') { {{ f[col.clave] | currency: 'ARS' : 'symbol-narrow' : '1.2-2' : 'es-AR' }} }
                          @case ('entero') { {{ f[col.clave] | number: '1.0-0' : 'es-AR' }} }
                          @default { {{ f[col.clave] }} }
                        }
                      </td>
                    </ng-container>
                  }
                  <tr mat-header-row *matHeaderRowDef="claves(tabla.columnas)"></tr>
                  <tr mat-row *matRowDef="let f; columns: claves(tabla.columnas)"></tr>
                </table>
              </div>
            </mat-expansion-panel>
          }
        </mat-accordion>

        <h2 class="titulo-seccion">Detalle</h2>
      }

      <app-data-table [columnas]="columnas()" [datos]="filas()" [cargando]="cargando()"
                      [conEditar]="false" [conBorrar]="false" textoVacio="No hay registros para los filtros elegidos." />
    </div>
  `,
  styles: `
    .tab-icono { margin-right: 8px; }
    mat-tab-group { margin-bottom: 16px; }
    .filtros { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px; align-items: center; }
    .atajos { grid-column: 1 / -1; display: flex; flex-wrap: wrap; gap: 4px; }
    .filtros-aplicados { display: flex; align-items: center; gap: 6px; margin: 16px 0 12px; }
    .error { display: flex; align-items: center; gap: 8px; color: var(--mat-sys-error); }
    .kpis { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 12px; margin-bottom: 16px; }
    .kpis mat-card-content { display: flex; flex-direction: column; gap: 4px; padding-top: 16px; }
    .label { font: var(--mat-sys-label-large); color: var(--mat-sys-on-surface-variant); }
    .valor { font: var(--mat-sys-headline-small); }
    .resumen { display: block; margin-bottom: 24px; }
    .scroll { overflow-x: auto; }
    .tabla-resumen { width: 100%; }
    .num { text-align: right; }
    .titulo-seccion { font: var(--mat-sys-title-large); margin: 0 0 12px; }
    mat-spinner { display: inline-block; margin-right: 8px; }
  `,
})
export class ReportesPage implements OnInit {
  private readonly api = inject(ApiService);
  private readonly notificacion = inject(NotificacionService);

  readonly tipos: TipoReporte[] = ['ventas', 'compras', 'canjes'];
  readonly tipo = signal<TipoReporte>('ventas');
  readonly indice = computed(() => this.tipos.indexOf(this.tipo()));

  readonly reporte = signal<Reporte | null>(null);
  readonly cargando = signal(true);
  readonly descargando = signal(false);
  readonly error = signal('');

  readonly clientes = toSignal(sinError(this.api.clientes()), { initialValue: [] as Cliente[] });
  readonly productos = toSignal(sinError(this.api.productos()), { initialValue: [] as Producto[] });
  readonly insumos = toSignal(sinError(this.api.insumos()), { initialValue: [] as Insumo[] });
  readonly proveedores = toSignal(sinError(this.api.proveedores()), { initialValue: [] as Proveedor[] });
  readonly ciudades = toSignal(sinError(this.api.ciudades()), { initialValue: [] as Ciudad[] });
  readonly usuarios = toSignal(sinError(this.api.usuarios()), { initialValue: [] as UsuarioResumen[] });

  readonly filtros = new FormGroup({
    desde: new FormControl<string | null>(hoy().slice(0, 8) + '01'),
    hasta: new FormControl<string | null>(hoy()),
    usuarioId: new FormControl<number | null>(null),
    clienteId: new FormControl<number | null>(null),
    productoId: new FormControl<number | null>(null),
    ciudadId: new FormControl<number | null>(null),
    proveedorId: new FormControl<number | null>(null),
    insumoId: new FormControl<number | null>(null),
  });

  readonly columnas = computed<Columna<Fila>[]>(() =>
    (this.reporte()?.columnas ?? []).map((c) => ({ clave: c.clave, titulo: c.titulo, tipo: TIPOS_TABLA[c.tipo] })),
  );
  readonly filas = computed(() => this.reporte()?.filas ?? []);

  constructor() {
    this.filtros.valueChanges.pipe(debounceTime(300), takeUntilDestroyed()).subscribe(() => this.cargar());
  }

  ngOnInit(): void {
    this.cargar();
  }

  muestra(filtro: (typeof FILTROS)[TipoReporte][number]): boolean {
    return FILTROS[this.tipo()].includes(filtro);
  }

  claves(columnas: { clave: string }[]): string[] {
    return columnas.map((c) => c.clave);
  }

  cambiarTipo(indice: number): void {
    this.tipo.set(this.tipos[indice]);
    this.reporte.set(null);
    // Los filtros que no aplican al nuevo reporte se limpian (sin disparar dos recargas)
    const limpiar: Record<string, null> = {};
    for (const campo of ['clienteId', 'productoId', 'ciudadId', 'proveedorId', 'insumoId'] as const) {
      if (!this.muestra(campo)) limpiar[campo] = null;
    }
    this.filtros.patchValue(limpiar, { emitEvent: false });
    this.cargar();
  }

  periodo(cual: 'mes' | 'anterior' | 'anio' | 'todo'): void {
    const d = new Date();
    const fmt = (x: Date) =>
      `${x.getFullYear()}-${String(x.getMonth() + 1).padStart(2, '0')}-${String(x.getDate()).padStart(2, '0')}`;
    switch (cual) {
      case 'mes':
        this.filtros.patchValue({ desde: fmt(new Date(d.getFullYear(), d.getMonth(), 1)), hasta: fmt(d) });
        break;
      case 'anterior':
        this.filtros.patchValue({
          desde: fmt(new Date(d.getFullYear(), d.getMonth() - 1, 1)),
          hasta: fmt(new Date(d.getFullYear(), d.getMonth(), 0)),
        });
        break;
      case 'anio':
        this.filtros.patchValue({ desde: fmt(new Date(d.getFullYear(), 0, 1)), hasta: fmt(d) });
        break;
      case 'todo':
        this.filtros.reset();
        break;
    }
  }

  cargar(): void {
    this.cargando.set(true);
    this.error.set('');
    this.api.reporte(this.tipo(), this.valoresFiltro()).subscribe({
      next: (r) => {
        this.reporte.set(r);
        this.cargando.set(false);
      },
      error: (err) => {
        this.cargando.set(false);
        this.reporte.set(null);
        this.error.set(mensajeDeError(err));
      },
    });
  }

  descargar(): void {
    this.descargando.set(true);
    this.api.reporteExcel(this.tipo(), this.valoresFiltro()).subscribe({
      next: (resp) => {
        this.descargando.set(false);
        const disposicion = resp.headers.get('Content-Disposition') ?? '';
        const nombre = /filename="?([^";]+)"?/.exec(disposicion)?.[1] ?? `reporte-${this.tipo()}.xlsx`;
        const url = URL.createObjectURL(resp.body!);
        const a = document.createElement('a');
        a.href = url;
        a.download = nombre;
        a.click();
        URL.revokeObjectURL(url);
        this.notificacion.ok(`Se descargó ${nombre}.`);
      },
      error: () => {
        this.descargando.set(false);
        this.notificacion.error(null, 'No se pudo generar el Excel. Revisá los filtros e intentá de nuevo.');
      },
    });
  }

  /** Solo los filtros que aplican al reporte elegido. */
  private valoresFiltro(): Record<string, string | number | null> {
    const v = this.filtros.getRawValue();
    const salida: Record<string, string | number | null> = { desde: v.desde, hasta: v.hasta, usuarioId: v.usuarioId };
    for (const campo of FILTROS[this.tipo()]) {
      salida[campo] = v[campo];
    }
    return salida;
  }
}
