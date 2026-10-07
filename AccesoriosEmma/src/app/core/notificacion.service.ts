import { HttpErrorResponse } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { MatSnackBar } from '@angular/material/snack-bar';

@Injectable({ providedIn: 'root' })
export class NotificacionService {
  private readonly snack = inject(MatSnackBar);

  ok(mensaje: string): void {
    this.snack.open(mensaje, 'Cerrar', { duration: 3000 });
  }

  error(err: unknown, porDefecto = 'Ocurrió un error. Intentá de nuevo.'): void {
    this.snack.open(mensajeDeError(err, porDefecto), 'Cerrar', { duration: 6000, panelClass: 'snack-error' });
  }
}

/** Extrae un mensaje legible de una respuesta de error del backend. */
export function mensajeDeError(err: unknown, porDefecto = 'Ocurrió un error. Intentá de nuevo.'): string {
  if (err instanceof HttpErrorResponse) {
    if (err.status === 0) {
      return 'No se pudo conectar con el servidor.';
    }
    const cuerpo = err.error as { message?: string } | null;
    if (cuerpo?.message) {
      return cuerpo.message;
    }
  }
  return porDefecto;
}

/** Errores de validación por campo devueltos por el backend (HTTP 422). */
export function erroresDeCampos(err: unknown): Record<string, string> {
  if (err instanceof HttpErrorResponse && err.status === 422) {
    return (err.error as { errors?: Record<string, string> })?.errors ?? {};
  }
  return {};
}
