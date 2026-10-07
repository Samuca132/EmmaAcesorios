import { HttpClient } from '@angular/common/http';
import { Injectable, computed, inject, signal } from '@angular/core';
import { Router } from '@angular/router';
import { Observable, tap } from 'rxjs';
import { environment } from '../../environments/environment';
import { LoginResponse, Usuario } from './models';

const CLAVE_SESION = 'emma.sesion';

interface Sesion {
  token: string;
  expira: number; // timestamp en ms
  usuario: Usuario;
}

@Injectable({ providedIn: 'root' })
export class AuthService {
  private readonly http = inject(HttpClient);
  private readonly router = inject(Router);
  private readonly sesion = signal<Sesion | null>(this.leerSesion());
  private timerExpiracion?: ReturnType<typeof setTimeout>;

  readonly usuario = computed(() => this.sesion()?.usuario ?? null);
  readonly autenticado = computed(() => this.sesion() !== null);
  /** Rol 1 = administrador. */
  readonly esAdmin = computed(() => this.sesion()?.usuario.rol === 1);

  constructor() {
    this.programarExpiracion();
  }

  get token(): string | null {
    const s = this.sesion();
    return s && s.expira > Date.now() ? s.token : null;
  }

  login(email: string, password: string): Observable<LoginResponse> {
    return this.http.post<LoginResponse>(`${environment.apiUrl}/login`, { email, password }).pipe(
      tap((r) => {
        const sesion: Sesion = { token: r.token, expira: Date.now() + r.expiraEn * 1000, usuario: r.usuario };
        // sessionStorage: la sesión se cierra al cerrar el navegador
        sessionStorage.setItem(CLAVE_SESION, JSON.stringify(sesion));
        this.sesion.set(sesion);
        this.programarExpiracion();
      }),
    );
  }

  cambiarPassword(actual: string, nueva: string): Observable<unknown> {
    return this.http.post(`${environment.apiUrl}/me/password`, { actual, nueva });
  }

  logout(motivo?: 'expirada'): void {
    sessionStorage.removeItem(CLAVE_SESION);
    this.sesion.set(null);
    clearTimeout(this.timerExpiracion);
    this.router.navigate(['/login'], motivo ? { queryParams: { motivo } } : {});
  }

  private programarExpiracion(): void {
    clearTimeout(this.timerExpiracion);
    const s = this.sesion();
    if (s) {
      this.timerExpiracion = setTimeout(() => this.logout('expirada'), Math.max(0, s.expira - Date.now()));
    }
  }

  private leerSesion(): Sesion | null {
    try {
      const s = JSON.parse(sessionStorage.getItem(CLAVE_SESION) ?? 'null') as Sesion | null;
      return s && s.expira > Date.now() ? s : null;
    } catch {
      return null;
    }
  }
}
