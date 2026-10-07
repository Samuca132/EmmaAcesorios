import { Directive, OnInit, inject, signal } from '@angular/core';
import { MatDialog } from '@angular/material/dialog';
import { filter, switchMap } from 'rxjs';
import { ApiService, Recurso } from '../core/api.service';
import { NotificacionService } from '../core/notificacion.service';
import { confirmar } from './confirm-dialog';
import { Columna } from './data-table';
import { CampoFormulario, FormDialog, FormDialogData } from './form-dialog';

/**
 * Lógica común de las pantallas de ABM (alta, baja, modificación):
 * carga del listado, modal de alta/edición y confirmación de borrado.
 */
@Directive()
export abstract class CrudPage<T extends { id: number; nombre: string }> implements OnInit {
  protected readonly api = inject(ApiService);
  protected readonly dialog = inject(MatDialog);
  protected readonly notificacion = inject(NotificacionService);

  readonly datos = signal<T[]>([]);
  readonly cargando = signal(true);

  abstract readonly recurso: Recurso;
  /** Nombre en singular, p. ej. "producto". */
  abstract readonly entidad: string;
  abstract readonly columnas: Columna<T>[];
  protected abstract campos(): CampoFormulario[];
  /** Valores iniciales del formulario al editar. */
  protected abstract valoresDe(item: T): Record<string, unknown>;

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
