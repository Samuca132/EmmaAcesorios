import { MatDialog } from '@angular/material/dialog';
import { Observable } from 'rxjs';
import { FormDialog, FormDialogData } from './form-dialog';

/**
 * Pide el motivo y anula la operación con `anular(motivo)`. Devuelve la
 * operación actualizada, o undefined si el usuario canceló.
 */
export function pedirAnulacion<T>(
  dialog: MatDialog,
  opciones: { titulo: string; mensaje: string; anular: (motivo: string) => Observable<T> },
): Observable<T | undefined> {
  return dialog
    .open<FormDialog<T>, FormDialogData<T>, T>(FormDialog, {
      width: '480px',
      data: {
        titulo: opciones.titulo,
        mensaje: opciones.mensaje,
        textoGuardar: 'Anular',
        campos: [
          {
            clave: 'motivo', etiqueta: 'Motivo', tipo: 'textoLargo', requerido: true, maxLength: 255, ancho: true,
            ayuda: 'Queda registrado junto con tu usuario.',
          },
        ],
        guardar: (v) => opciones.anular(v['motivo'] as string),
      },
    })
    .afterClosed();
}
