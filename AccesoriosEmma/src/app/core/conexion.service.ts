import { DestroyRef, Injectable, inject, signal } from '@angular/core';
import { MatSnackBar } from '@angular/material/snack-bar';
import { SwUpdate, VersionReadyEvent } from '@angular/service-worker';
import { filter } from 'rxjs';

/**
 * App instalable:
 *  - `enLinea` refleja si hay internet (sin conexión no se puede vender: el
 *    stock tiene que estar al día, así que la app avisa en lugar de guardar
 *    datos viejos);
 *  - cuando se publica una versión nueva, ofrece recargar.
 */
@Injectable({ providedIn: 'root' })
export class ConexionService {
  readonly enLinea = signal(typeof navigator === 'undefined' ? true : navigator.onLine);

  constructor() {
    const actualizar = () => this.enLinea.set(navigator.onLine);
    window.addEventListener('online', actualizar);
    window.addEventListener('offline', actualizar);
    inject(DestroyRef).onDestroy(() => {
      window.removeEventListener('online', actualizar);
      window.removeEventListener('offline', actualizar);
    });

    const sw = inject(SwUpdate);
    if (sw.isEnabled) {
      const snack = inject(MatSnackBar);
      sw.versionUpdates
        .pipe(filter((e): e is VersionReadyEvent => e.type === 'VERSION_READY'))
        .subscribe(() => {
          snack
            .open('Hay una versión nueva del sistema.', 'Actualizar')
            .onAction()
            .subscribe(() => document.location.reload());
        });
    }
  }
}
