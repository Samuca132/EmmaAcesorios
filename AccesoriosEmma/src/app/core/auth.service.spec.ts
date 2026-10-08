import { HttpClient, provideHttpClient, withInterceptors } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';
import { provideRouter, Router } from '@angular/router';
import { environment } from '../../environments/environment';
import { AuthService } from './auth.service';
import { authInterceptor } from './auth.interceptor';
import { LoginResponse } from './models';

const API = environment.apiUrl;

const respuestaLogin = (rol: number, expiraEn = 3600): LoginResponse =>
  ({ token: 'token-de-prueba', expiraEn, usuario: { id: 1, nombre: 'Ana', email: 'ana@emma.test', rol } }) as LoginResponse;

describe('AuthService', () => {
  let auth: AuthService;
  let http: HttpTestingController;
  let router: Router;

  beforeEach(() => {
    sessionStorage.clear();
    TestBed.configureTestingModule({
      providers: [provideRouter([]), provideHttpClient(withInterceptors([authInterceptor])), provideHttpClientTesting()],
    });
    auth = TestBed.inject(AuthService);
    http = TestBed.inject(HttpTestingController);
    router = TestBed.inject(Router);
    vi.spyOn(router, 'navigate').mockResolvedValue(true);
  });

  afterEach(() => http.verify());

  const iniciarSesion = (rol = 2, expiraEn = 3600) => {
    auth.login('ana@emma.test', 'clave-de-prueba-123').subscribe();
    http.expectOne(`${API}/login`).flush(respuestaLogin(rol, expiraEn));
  };

  it('arranca sin sesión', () => {
    expect(auth.autenticado()).toBe(false);
    expect(auth.token).toBeNull();
  });

  it('al iniciar sesión guarda el token y los datos del usuario', () => {
    iniciarSesion();

    expect(auth.autenticado()).toBe(true);
    expect(auth.usuario()?.nombre).toBe('Ana');
    expect(auth.token).toBe('token-de-prueba');
    expect(JSON.parse(sessionStorage.getItem('emma.sesion')!).token).toBe('token-de-prueba');
  });

  it('solo el rol 1 es administrador', () => {
    iniciarSesion(2);
    expect(auth.esAdmin()).toBe(false);

    iniciarSesion(1);
    expect(auth.esAdmin()).toBe(true);
  });

  it('el interceptor agrega el token a la API, pero no al login', () => {
    iniciarSesion();

    TestBed.inject(HttpClient).get(`${API}/productos`).subscribe();
    expect(http.expectOne(`${API}/productos`).request.headers.get('Authorization')).toBe('Bearer token-de-prueba');

    auth.login('ana@emma.test', 'x').subscribe();
    const login = http.expectOne(`${API}/login`);
    expect(login.request.headers.has('Authorization')).toBe(false);
    login.flush(respuestaLogin(2));
  });

  it('un 401 de la API cierra la sesión y lleva al login', () => {
    iniciarSesion();

    TestBed.inject(HttpClient).get(`${API}/productos`).subscribe({ error: () => undefined });
    http.expectOne(`${API}/productos`).flush({ message: 'Token vencido' }, { status: 401, statusText: 'Unauthorized' });

    expect(auth.autenticado()).toBe(false);
    expect(sessionStorage.getItem('emma.sesion')).toBeNull();
    expect(router.navigate).toHaveBeenCalledWith(['/login'], { queryParams: { motivo: 'expirada' } });
  });

  it('la sesión se cierra sola cuando vence el token', () => {
    vi.useFakeTimers();
    try {
      iniciarSesion(2, 60);
      vi.advanceTimersByTime(59_000);
      expect(auth.autenticado()).toBe(true);

      vi.advanceTimersByTime(2_000);
      expect(auth.autenticado()).toBe(false);
    } finally {
      vi.useRealTimers();
    }
  });
});
