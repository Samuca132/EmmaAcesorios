import { DOCUMENT } from '@angular/common';
import { Injectable, effect, inject, signal } from '@angular/core';

const CLAVE = 'emma.tema';

/**
 * Modo claro / oscuro. La preferencia se guarda en este navegador; si el
 * usuario nunca eligió, se sigue la del sistema operativo.
 */
@Injectable({ providedIn: 'root' })
export class TemaService {
  private readonly html = inject(DOCUMENT).documentElement;

  readonly oscuro = signal(this.preferenciaInicial());

  constructor() {
    effect(() => {
      // styles.scss usa theme-type: color-scheme, así que alcanza con cambiar esto
      this.html.style.colorScheme = this.oscuro() ? 'dark' : 'light';
    });
  }

  alternar(): void {
    const cambiar = () => {
      this.oscuro.update((v) => !v);
      // aplicar ya (sin esperar al effect) para que la transición capture el estado nuevo
      this.html.style.colorScheme = this.oscuro() ? 'dark' : 'light';
      this.guardar();
    };

    // Fundido suave entre los dos temas con la View Transitions API. Si el
    // navegador no la soporta o el usuario pidió menos animaciones, cambia al instante.
    const doc = this.html.ownerDocument;
    if (doc.startViewTransition && !matchMedia('(prefers-reduced-motion: reduce)').matches) {
      doc.startViewTransition(cambiar);
    } else {
      cambiar();
    }
  }

  private guardar(): void {
    try {
      localStorage.setItem(CLAVE, this.oscuro() ? 'oscuro' : 'claro');
    } catch {
      // sin almacenamiento (modo privado): vale solo para esta sesión
    }
  }

  private preferenciaInicial(): boolean {
    try {
      const guardado = localStorage.getItem(CLAVE);
      if (guardado) return guardado === 'oscuro';
    } catch {
      // ignorar
    }
    return matchMedia('(prefers-color-scheme: dark)').matches;
  }
}
