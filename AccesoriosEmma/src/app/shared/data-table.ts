import { CurrencyPipe, DatePipe, DecimalPipe } from '@angular/common';
import {
  AfterViewInit, Component, computed, effect, input, output, viewChild,
} from '@angular/core';
import { MatButtonModule } from '@angular/material/button';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatIconModule } from '@angular/material/icon';
import { MatInputModule } from '@angular/material/input';
import { MatMenuModule } from '@angular/material/menu';
import { MatPaginator, MatPaginatorModule } from '@angular/material/paginator';
import { MatProgressBarModule } from '@angular/material/progress-bar';
import { MatSort, MatSortModule } from '@angular/material/sort';
import { MatTableDataSource, MatTableModule } from '@angular/material/table';
import { MatTooltipModule } from '@angular/material/tooltip';

export type TipoColumna = 'texto' | 'numero' | 'moneda' | 'fecha' | 'fechaHora' | 'porcentaje';

export interface Columna<T> {
  clave: keyof T & string;
  titulo: string;
  tipo?: TipoColumna;
  /** Resalta en rojo los valores menores o iguales a este número (p. ej. stock bajo). */
  alertaSi?: number;
}

/**
 * Listado genérico Material: búsqueda, orden por columna, paginado y menú
 * de acciones por fila. Reemplaza a las antiguas "cards" con imagen.
 */
@Component({
  selector: 'app-data-table',
  imports: [
    MatTableModule, MatSortModule, MatPaginatorModule, MatFormFieldModule, MatInputModule,
    MatIconModule, MatButtonModule, MatMenuModule, MatTooltipModule, MatProgressBarModule,
    CurrencyPipe, DecimalPipe, DatePipe,
  ],
  template: `
    <div class="toolbar">
      <mat-form-field appearance="outline" subscriptSizing="dynamic" class="buscador">
        <mat-icon matPrefix>search</mat-icon>
        <mat-label>Buscar</mat-label>
        <input matInput #filtro (input)="filtrar(filtro.value)" autocomplete="off" />
        @if (filtro.value) {
          <button matSuffix mat-icon-button aria-label="Limpiar búsqueda" (click)="filtro.value = ''; filtrar('')">
            <mat-icon>close</mat-icon>
          </button>
        }
      </mat-form-field>
      <ng-content />
    </div>

    <div class="tabla mat-elevation-z0">
      @if (cargando()) {
        <mat-progress-bar mode="indeterminate" />
      }
      <div class="scroll">
        <table mat-table [dataSource]="dataSource" matSort>
          @for (col of columnas(); track col.clave) {
            <ng-container [matColumnDef]="col.clave">
              <th mat-header-cell *matHeaderCellDef mat-sort-header [class.num]="esNumerica(col)">{{ col.titulo }}</th>
              <td mat-cell *matCellDef="let fila" [class.num]="esNumerica(col)"
                  [class.alerta]="col.alertaSi !== undefined && fila[col.clave] <= col.alertaSi">
                @if (fila[col.clave] === null || fila[col.clave] === undefined || fila[col.clave] === '') {
                  —
                } @else {
                @switch (col.tipo) {
                  @case ('moneda') { {{ fila[col.clave] | currency: 'ARS' : 'symbol-narrow' : '1.2-2' : 'es-AR' }} }
                  @case ('numero') { {{ fila[col.clave] | number: '1.0-2' : 'es-AR' }} }
                  @case ('porcentaje') { {{ fila[col.clave] }}% }
                  @case ('fecha') { {{ fila[col.clave] | date: 'dd/MM/yyyy' }} }
                  @case ('fechaHora') { {{ fila[col.clave] | date: 'dd/MM/yyyy HH:mm' }} }
                  @default { {{ fila[col.clave] }} }
                }
                }
              </td>
            </ng-container>
          }

          <ng-container matColumnDef="_acciones">
            <th mat-header-cell *matHeaderCellDef></th>
            <td mat-cell *matCellDef="let fila" class="acciones">
              @if (conVer()) {
                <button mat-icon-button [matTooltip]="textoVer()" (click)="$event.stopPropagation(); ver.emit(fila)">
                  <mat-icon>{{ iconoVer() }}</mat-icon>
                </button>
              }
              @if (conEditar() || conBorrar()) {
                <button mat-icon-button [matMenuTriggerFor]="menu" aria-label="Acciones" (click)="$event.stopPropagation()">
                  <mat-icon>more_vert</mat-icon>
                </button>
                <mat-menu #menu="matMenu" xPosition="before">
                  @if (conEditar()) {
                    <button mat-menu-item (click)="editar.emit(fila)"><mat-icon>edit</mat-icon>Editar</button>
                  }
                  @if (conBorrar()) {
                    <button mat-menu-item (click)="borrar.emit(fila)"><mat-icon>delete</mat-icon>Borrar</button>
                  }
                </mat-menu>
              }
            </td>
          </ng-container>

          <tr mat-header-row *matHeaderRowDef="claves(); sticky: true"></tr>
          <tr mat-row *matRowDef="let fila; columns: claves()" [class.clickable]="conVer()"
              (click)="conVer() && ver.emit(fila)"></tr>
          <tr class="mat-row" *matNoDataRow>
            <td class="vacio" [attr.colspan]="claves().length">
              {{ cargando() ? 'Cargando…' : (filtro.value ? 'No hay resultados para "' + filtro.value + '".' : textoVacio()) }}
            </td>
          </tr>
        </table>
      </div>
      <mat-paginator [pageSizeOptions]="[10, 25, 50, 100]" [pageSize]="10" showFirstLastButtons
                     aria-label="Seleccionar página" />
    </div>
  `,
  styles: `
    :host { display: block; }
    .toolbar { display: flex; gap: 12px; align-items: center; flex-wrap: wrap; margin-bottom: 16px; }
    .buscador { flex: 1 1 260px; max-width: 420px; }
    .tabla {
      border-radius: var(--mat-sys-corner-large);
      overflow: hidden;
      background: var(--mat-sys-surface);
      border: 1px solid var(--mat-sys-outline-variant);
    }
    .scroll { overflow-x: auto; }
    table { width: 100%; }
    .num { text-align: right; }
    th.num ::ng-deep .mat-sort-header-container { justify-content: flex-end; }
    .acciones { width: 1%; white-space: nowrap; text-align: right; }
    .alerta { color: var(--mat-sys-error); font-weight: 500; }
    .clickable { cursor: pointer; }
    .clickable:hover { background: var(--mat-sys-surface-container-low); }
    .vacio { padding: 32px 16px; text-align: center; color: var(--mat-sys-on-surface-variant); }
  `,
})
export class DataTable<T> implements AfterViewInit {
  readonly columnas = input.required<Columna<T>[]>();
  readonly datos = input<T[]>([]);
  readonly cargando = input(false);
  readonly conEditar = input(true);
  readonly conBorrar = input(true);
  readonly conVer = input(false);
  readonly iconoVer = input('visibility');
  readonly textoVer = input('Ver detalle');
  readonly textoVacio = input('Todavía no hay registros.');

  readonly editar = output<T>();
  readonly borrar = output<T>();
  readonly ver = output<T>();

  private readonly sort = viewChild.required(MatSort);
  private readonly paginator = viewChild.required(MatPaginator);

  readonly dataSource = new MatTableDataSource<T>([]);
  readonly claves = computed(() => {
    const claves: string[] = this.columnas().map((c) => c.clave);
    return this.conEditar() || this.conBorrar() || this.conVer() ? [...claves, '_acciones'] : claves;
  });

  constructor() {
    effect(() => {
      this.dataSource.data = this.datos();
    });
    // Búsqueda sin distinguir mayúsculas ni acentos
    this.dataSource.filterPredicate = (fila, filtro) =>
      normalizar(Object.values(fila as object).join(' ')).includes(filtro);
  }

  ngAfterViewInit(): void {
    this.dataSource.sort = this.sort();
    this.dataSource.paginator = this.paginator();
  }

  filtrar(valor: string): void {
    this.dataSource.filter = normalizar(valor.trim());
    this.dataSource.paginator?.firstPage();
  }

  esNumerica(col: Columna<T>): boolean {
    return col.tipo === 'numero' || col.tipo === 'moneda' || col.tipo === 'porcentaje';
  }
}

function normalizar(texto: string): string {
  return texto.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');
}
