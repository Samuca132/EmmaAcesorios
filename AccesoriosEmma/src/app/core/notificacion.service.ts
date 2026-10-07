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
    if (err.status === 0 || err.status === 502 || err.status === 503 || err.status === 504) {
      return 'No se pudo conectar con el servidor. Verificá que el backend esté funcionando.';
    }
    if (err.status >= 200 && err.status < 300) {
      // Llegó una respuesta "OK" que no es JSON: casi siempre es una página HTML
      // porque apiUrl no apunta al backend (o el proxy de desarrollo no está activo).
      return 'El servidor respondió algo que no es la API. Revisá que el backend esté levantado y la configuración de apiUrl.';
    }
    const cuerpo = err.error as { message?: string } | null;
    if (cuerpo?.message) {
      return cuerpo.message;
    }
    if (err.status >= 500) {
      // Error sin respuesta de la API (p. ej. el proxy de desarrollo no llega al backend)
      return 'No se pudo conectar con el servidor. Verificá que el backend esté funcionando.';
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
