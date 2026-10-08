import { DatePipe } from '@angular/common';
import { Component, OnInit, inject, input, signal } from '@angular/core';
import { toSignal } from '@angular/core/rxjs-interop';
import { FormControl, FormGroup, ReactiveFormsModule } from '@angular/forms';
import { MatButtonModule } from '@angular/material/button';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatIconModule } from '@angular/material/icon';
import { MatInputModule } from '@angular/material/input';
import { MatPaginatorModule, PageEvent } from '@angular/material/paginator';
import { MatProgressBarModule } from '@angular/material/progress-bar';
import { MatSelectModule } from '@angular/material/select';
import { MatTableModule } from '@angular/material/table';
import { debounceTime } from 'rxjs';
import { ApiService, sinError } from '../../core/api.service';
import { AccionAuditoria, PaginaAuditoria, RegistroAuditoria, UsuarioResumen } from '../../core/models';
import { NotificacionService } from '../../core/notificacion.service';

/** Nombres legibles de los campos que aparecen en "cambios". */
const CAMPOS: Record<string, string> = {
  nombre: 'Nombre', stock: 'Stock', precio: 'Precio', coste: 'Coste', telefono: 'Teléfono', ciudad: 'Ciudad',
  provincia: 'Provincia', descuentoCanje: 'Descuento canje', email: 'Email', rol: 'Rol', activo: 'Activo',
  password: 'Contraseña', cliente: 'Cliente', total: 'Total', cantidadProductos: 'Unidades', fecha: 'Fecha',
  proveedor: 'Proveedor', insumo: 'Insumo', producto: 'Producto', cantidad: 'Cantidad', costo: 'Costo',
  cantidadProducto: 'Cant. producto', cantidadInsumo: 'Cant. insumo', profit: 'Ganancia', usuario: 'Registró',
  stockMinimo: 'Stock mínimo', costoPromedio: 'Costo promedio', costoAdicional: 'Costo adicional',
};

const ICONOS: Record<AccionAuditoria, string> = {
  crear: 'add_circle', editar: 'edit', borrar: 'delete', anular: 'block',
};

/**
 * Configuración → Historial: quién cambió qué y cuándo. Paginado en el
 * servidor (puede tener miles de registros).
 *
 * Con `entidad` y `entidadId` muestra solo el historial de un registro.
 */
@Component({
  selector: 'app-historial',
  imports: [
    ReactiveFormsModule, MatFormFieldModule, MatInputModule, MatSelectModule, MatButtonModule, MatIconModule,
    MatTableModule, MatPaginatorModule, MatProgressBarModule, DatePipe,
  ],
  template: `
    @if (!entidadFija()) {
      <form class="filtros" [formGroup]="filtros">
        <mat-form-field appearance="outline" subscriptSizing="dynamic">
          <mat-label>Desde</mat-label>
          <input matInput type="date" formControlName="desde" />
        </mat-form-field>
        <mat-form-field appearance="outline" subscriptSizing="dynamic">
          <mat-label>Hasta</mat-label>
          <input matInput type="date" formControlName="hasta" />
        </mat-form-field>
        <mat-form-field appearance="outline" subscriptSizing="dynamic">
          <mat-label>Usuario</mat-label>
          <mat-select formControlName="usuarioId">
            <mat-option [value]="null">Todos</mat-option>
            @for (u of usuarios(); track u.id) { <mat-option [value]="u.id">{{ u.nombre }}</mat-option> }
          </mat-select>
        </mat-form-field>
        <mat-form-field appearance="outline" subscriptSizing="dynamic">
          <mat-label>Tipo de registro</mat-label>
          <mat-select formControlName="entidad">
            <mat-option [value]="null">Todos</mat-option>
            @for (e of pagina()?.entidades ?? []; track e) { <mat-option [value]="e">{{ e }}</mat-option> }
          </mat-select>
        </mat-form-field>
        <mat-form-field appearance="outline" subscriptSizing="dynamic">
          <mat-label>Acción</mat-label>
          <mat-select formControlName="accion">
            <mat-option [value]="null">Todas</mat-option>
            @for (a of pagina()?.acciones ?? []; track a.id) { <mat-option [value]="a.id">{{ a.nombre }}</mat-option> }
          </mat-select>
        </mat-form-field>
        <button mat-button type="button" (click)="filtros.reset()"><mat-icon>filter_alt_off</mat-icon>Limpiar</button>
      </form>
    }

    <div class="tabla">
      @if (cargando()) { <mat-progress-bar mode="indeterminate" /> }
      <div class="scroll">
        <table mat-table [dataSource]="pagina()?.items ?? []">
          <ng-container matColumnDef="fecha">
            <th mat-header-cell *matHeaderCellDef>Fecha</th>
            <td mat-cell *matCellDef="let r" class="nowrap">{{ r.fecha | date: 'dd/MM/yyyy HH:mm' }}</td>
          </ng-container>
          <ng-container matColumnDef="usuario">
            <th mat-header-cell *matHeaderCellDef>Usuario</th>
            <td mat-cell *matCellDef="let r">{{ r.usuario ?? 'Sistema' }}</td>
          </ng-container>
          <ng-container matColumnDef="accion">
            <th mat-header-cell *matHeaderCellDef>Acción</th>
            <td mat-cell *matCellDef="let r" class="nowrap">
              <span class="accion" [class]="r.accion"><mat-icon>{{ icono(r.accion) }}</mat-icon>{{ r.accionNombre }}</span>
            </td>
          </ng-container>
          <ng-container matColumnDef="registro">
            <th mat-header-cell *matHeaderCellDef>Registro</th>
            <td mat-cell *matCellDef="let r"><span class="text-muted">{{ r.entidad }}</span> · {{ r.descripcion }}</td>
          </ng-container>
          <ng-container matColumnDef="detalle">
            <th mat-header-cell *matHeaderCellDef>Detalle</th>
            <td mat-cell *matCellDef="let r" class="detalle">
              @if (r.motivo) { <div><strong>Motivo:</strong> {{ r.motivo }}</div> }
              @if (r.accion === 'editar') {
                @for (c of cambios(r); track c.campo) {
                  <div><strong>{{ c.campo }}:</strong> {{ c.antes }} <mat-icon class="flecha">arrow_forward</mat-icon> {{ c.despues }}</div>
                }
              }
            </td>
          </ng-container>
          <tr mat-header-row *matHeaderRowDef="columnas; sticky: true"></tr>
          <tr mat-row *matRowDef="let r; columns: columnas"></tr>
          <tr class="mat-row" *matNoDataRow>
            <td class="vacio" [attr.colspan]="columnas.length">{{ cargando() ? 'Cargando…' : 'No hay cambios registrados.' }}</td>
          </tr>
        </table>
      </div>
      <mat-paginator [length]="pagina()?.total ?? 0" [pageSize]="pagina()?.porPagina ?? 50" [pageIndex]="indice()"
                     [hidePageSize]="true" showFirstLastButtons (page)="cambiarPagina($event)" />
    </div>
  `,
  styles: `
    :host { display: block; }
    .filtros { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 12px; align-items: center; margin-bottom: 16px; }
    .tabla { border: 1px solid var(--mat-sys-outline-variant); border-radius: var(--mat-sys-corner-large); overflow: hidden; background: var(--mat-sys-surface); }
    .scroll { overflow-x: auto; }
    table { width: 100%; }
    .nowrap { white-space: nowrap; }
    .detalle { font: var(--mat-sys-body-small); padding-top: 6px; padding-bottom: 6px; }
    .flecha { font-size: 14px; width: 14px; height: 14px; vertical-align: middle; }
    .accion { display: inline-flex; align-items: center; gap: 4px; }
    .accion mat-icon { font-size: 18px; width: 18px; height: 18px; }
    .accion.crear { color: var(--mat-sys-primary); }
    .accion.borrar, .accion.anular { color: var(--mat-sys-error); }
    .vacio { padding: 32px 16px; text-align: center; color: var(--mat-sys-on-surface-variant); }
  `,
})
export class Historial implements OnInit {
  private readonly api = inject(ApiService);
  private readonly notificacion = inject(NotificacionService);

  /** Para ver el historial de un solo registro (p. ej. desde la ficha de un producto). */
  readonly entidadFija = input<string | null>(null);
  readonly entidadIdFijo = input<number | null>(null);

  readonly usuarios = toSignal(sinError(this.api.usuarios()), { initialValue: [] as UsuarioResumen[] });
  readonly pagina = signal<PaginaAuditoria | null>(null);
  readonly cargando = signal(true);
  readonly indice = signal(0);
  readonly columnas = ['fecha', 'usuario', 'accion', 'registro', 'detalle'];

  readonly filtros = new FormGroup({
    desde: new FormControl<string | null>(null),
    hasta: new FormControl<string | null>(null),
    usuarioId: new FormControl<number | null>(null),
    entidad: new FormControl<string | null>(null),
    accion: new FormControl<AccionAuditoria | null>(null),
  });

  ngOnInit(): void {
    this.cargar();
    this.filtros.valueChanges.pipe(debounceTime(250)).subscribe(() => {
      this.indice.set(0);
      this.cargar();
    });
  }

  cambiarPagina(e: PageEvent): void {
    this.indice.set(e.pageIndex);
    this.cargar();
  }

  cargar(): void {
    this.cargando.set(true);
    const filtros = this.entidadFija()
      ? { entidad: this.entidadFija(), entidadId: this.entidadIdFijo() }
      : this.filtros.getRawValue();
    this.api.auditoria({ ...filtros, pagina: this.indice() + 1 }).subscribe({
      next: (p) => {
        this.pagina.set(p);
        this.cargando.set(false);
      },
      error: (err) => {
        this.cargando.set(false);
        this.notificacion.error(err);
      },
    });
  }

  icono(accion: AccionAuditoria): string {
    return ICONOS[accion] ?? 'history';
  }

  cambios(r: RegistroAuditoria): { campo: string; antes: string; despues: string }[] {
    return Object.entries(r.cambios).map(([campo, [antes, despues]]) => ({
      campo: CAMPOS[campo] ?? campo,
      antes: mostrar(antes),
      despues: mostrar(despues),
    }));
  }
}

function mostrar(valor: unknown): string {
  if (valor === null || valor === undefined || valor === '') return '—';
  if (valor === true) return 'Sí';
  if (valor === false) return 'No';
  return String(valor);
}
