import { Directive, OnInit, computed, inject, signal } from '@angular/core';
import { MatDialog } from '@angular/material/dialog';
import { filter, switchMap } from 'rxjs';
import { ApiService, Recurso } from '../core/api.service';
import { AuthService } from '../core/auth.service';
import { HistorialDialog, HistorialDialogData } from '../pages/configuracion/historial-dialog';
import { NotificacionService } from '../core/notificacion.service';
import { confirmar } from './confirm-dialog';
import { AccionFila, Columna } from './data-table';
import { CampoFormulario, FormDialog, FormDialogData } from './form-dialog';

/**
 * Lógica común de las pantallas de ABM (alta, baja, modificación):
 * carga del listado, modal de alta/edición y confirmación de borrado.
 */
@Directive()
export abstract class CrudPage<T extends { id: number; nombre: string }, Fila extends T = T> implements OnInit {
  protected readonly api = inject(ApiService);
  protected readonly dialog = inject(MatDialog);
  protected readonly notificacion = inject(NotificacionService);
  private readonly auth = inject(AuthService);

  readonly datos = signal<T[]>([]);
  readonly cargando = signal(true);

  abstract readonly recurso: Recurso;
  /** Nombre en singular, p. ej. "producto". */
  abstract readonly entidad: string;
  /** Fila: el tipo que se muestra, si la pantalla le agrega columnas calculadas. */
  abstract readonly columnas: Columna<Fila>[];
  protected abstract campos(): CampoFormulario[];
  /** Valores iniciales del formulario al editar. */
  protected abstract valoresDe(item: T): Record<string, unknown>;
  /** Valores iniciales del formulario de alta. */
  protected valoresNuevo(): Record<string, unknown> {
    return {};
  }

  /** Acciones del menú ⋮ propias de cada pantalla (además de Editar y Borrar). */
  protected accionesExtra(): AccionFila<T>[] {
    return [];
  }

  /** Atiende las acciones de accionesExtra(). */
  protected alAccion(id: string, item: T): void {
    void id;
    void item;
  }

  /** "Ver historial" solo para administradores (el backend también lo restringe). */
  readonly acciones = computed<AccionFila<T>[]>(() => [
    ...this.accionesExtra(),
    ...(this.auth.esAdmin() ? [{ id: 'historial', texto: 'Ver historial', icono: 'history' }] : []),
  ]);

  accion({ id, fila }: { id: string; fila: T }): void {
    if (id === 'historial') {
      this.dialog.open<HistorialDialog, HistorialDialogData>(HistorialDialog, {
        width: '960px',
        maxWidth: '95vw',
        data: {
          // mismo nombre que usa el backend en la auditoría: "Producto", "Cliente"…
          entidad: this.entidad.charAt(0).toUpperCase() + this.entidad.slice(1),
          entidadId: fila.id,
          nombre: fila.nombre,
        },
      });
    } else {
      this.alAccion(id, fila);
    }
  }

  ngOnInit(): void {
    this.cargar();
  }

  cargar(): void {
    this.cargando.set(true);
    this.api.listar<T>(this.recurso).subscribe({
      next: (datos) => {
        this.datos.set(datos);
        this.cargando.set(false);
      },
      error: (err) => {
        this.cargando.set(false);
        this.notificacion.error(err);
      },
    });
  }

  nuevo(): void {
    this.abrir({
      titulo: `Nuevo ${this.entidad}`,
      campos: this.campos(),
      valores: this.valoresNuevo(),
      textoGuardar: 'Crear',
      guardar: (v) => this.api.crear<T>(this.recurso, v),
    }).subscribe((creado) => {
      this.notificacion.ok(`Se creó "${creado.nombre}".`);
      this.cargar();
    });
  }

  editar(item: T): void {
    this.abrir({
      titulo: `Editar ${this.entidad}`,
      campos: this.campos(),
      valores: this.valoresDe(item),
      guardar: (v) => this.api.actualizar<T>(this.recurso, item.id, v),
    }).subscribe(() => {
      this.notificacion.ok('Cambios guardados.');
      this.cargar();
    });
  }

  borrar(item: T): void {
    confirmar(this.dialog, {
      titulo: `Borrar ${this.entidad}`,
      mensaje: `¿Seguro que querés borrar "${item.nombre}"?`,
      confirmar: 'Borrar',
    })
      .pipe(
        filter(Boolean),
        switchMap(() => this.api.borrar(this.recurso, item.id)),
      )
      .subscribe({
        next: () => {
          this.notificacion.ok(`Se borró "${item.nombre}".`);
          this.cargar();
        },
        error: (err) => this.notificacion.error(err),
      });
  }

  private abrir(data: FormDialogData<T>) {
    return this.dialog
      .open<FormDialog<T>, FormDialogData<T>, T>(FormDialog, { data })
      .afterClosed()
      .pipe(filter((r): r is T => !!r));
  }
}
