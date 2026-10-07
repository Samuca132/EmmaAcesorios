import { HttpClient, HttpParams, HttpResponse } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable, catchError, of } from 'rxjs';
import { environment } from '../../environments/environment';
import {
  Canje, Ciudad, Cliente, Compra, Dashboard, Insumo, Producto, Proveedor, Provincia, Reporte, Ticket,
  TipoReporte, UsuarioResumen, NuevoUsuario, Rol, UsuarioAdmin,
} from './models';

/**
 * Para listas de opciones (selects): si la API falla devuelve una lista vacía
 * en lugar de propagar el error, que con toSignal() rompería el renderizado.
 */
export function sinError<T>(fuente: Observable<T[]>): Observable<T[]> {
  return fuente.pipe(catchError(() => of([] as T[])));
}

/** Recursos con CRUD completo en el backend. */
export type Recurso = 'productos' | 'insumos' | 'clientes' | 'proveedores' | 'ciudades';

type Filtros = Record<string, string | number | null | undefined>;

/**
 * Único punto de acceso a la API REST del backend Symfony.
 */
@Injectable({ providedIn: 'root' })
export class ApiService {
  private readonly http = inject(HttpClient);
  private readonly url = environment.apiUrl;

  // ---------- CRUD genérico ----------
  listar<T>(recurso: Recurso): Observable<T[]> {
    return this.http.get<T[]>(`${this.url}/${recurso}`);
  }

  obtener<T>(recurso: Recurso, id: number): Observable<T> {
    return this.http.get<T>(`${this.url}/${recurso}/${id}`);
  }

  crear<T>(recurso: Recurso, datos: unknown): Observable<T> {
    return this.http.post<T>(`${this.url}/${recurso}`, datos);
  }

  actualizar<T>(recurso: Recurso, id: number, datos: unknown): Observable<T> {
    return this.http.put<T>(`${this.url}/${recurso}/${id}`, datos);
  }

  borrar(recurso: Recurso, id: number): Observable<void> {
    return this.http.delete<void>(`${this.url}/${recurso}/${id}`);
  }

  // ---------- Atajos tipados ----------
  productos = () => this.listar<Producto>('productos');
  insumos = () => this.listar<Insumo>('insumos');
  clientes = () => this.listar<Cliente>('clientes');
  proveedores = () => this.listar<Proveedor>('proveedores');
  ciudades = () => this.listar<Ciudad>('ciudades');

  provincias(): Observable<Provincia[]> {
    return this.http.get<Provincia[]>(`${this.url}/provincias`);
  }

  dashboard(): Observable<Dashboard> {
    return this.http.get<Dashboard>(`${this.url}/dashboard`);
  }

  // ---------- Operaciones ----------
  compras(filtros: Filtros = {}): Observable<Compra[]> {
    return this.http.get<Compra[]>(`${this.url}/compras`, { params: this.params(filtros) });
  }

  /** Registra una o más compras al mismo proveedor en una sola operación. */
  registrarCompras(datos: {
    proveedorId: number;
    fecha: string | null;
    items: { insumoId: number; cantidad: number; costo: number }[];
  }): Observable<Compra[]> {
    return this.http.post<Compra[]>(`${this.url}/compras`, datos);
  }

  canjes(): Observable<Canje[]> {
    return this.http.get<Canje[]>(`${this.url}/canjes`);
  }

  /** Registra uno o más canjes con el mismo proveedor en una sola operación. */
  registrarCanjes(datos: {
    proveedorId: number;
    descuentoProducto: number | null;
    descuentoInsumo: number | null;
    items: { productoId: number; cantidadProducto: number; insumoId: number; cantidadInsumo: number }[];
  }): Observable<Canje[]> {
    return this.http.post<Canje[]>(`${this.url}/canjes`, datos);
  }

  ventas(filtros: Filtros = {}): Observable<Ticket[]> {
    return this.http.get<Ticket[]>(`${this.url}/ventas`, { params: this.params(filtros) });
  }

  ticket(id: number): Observable<Ticket> {
    return this.http.get<Ticket>(`${this.url}/ventas/${id}`);
  }

  registrarVenta(datos: { clienteId: number; items: { productoId: number; cantidad: number }[] }): Observable<Ticket> {
    return this.http.post<Ticket>(`${this.url}/ventas`, datos);
  }

  // ---------- Configuración (solo administradores) ----------
  adminUsuarios(): Observable<UsuarioAdmin[]> {
    return this.http.get<UsuarioAdmin[]>(`${this.url}/admin/usuarios`);
  }

  adminRoles(): Observable<Rol[]> {
    return this.http.get<Rol[]>(`${this.url}/admin/roles`);
  }

  crearUsuario(datos: NuevoUsuario): Observable<UsuarioAdmin> {
    return this.http.post<UsuarioAdmin>(`${this.url}/admin/usuarios`, datos);
  }

  // ---------- Reportes ----------
  usuarios(): Observable<UsuarioResumen[]> {
    return this.http.get<UsuarioResumen[]>(`${this.url}/usuarios`);
  }

  reporte(tipo: TipoReporte, filtros: Filtros): Observable<Reporte> {
    return this.http.get<Reporte>(`${this.url}/reportes/${tipo}`, { params: this.params(filtros) });
  }

  /** Descarga el reporte como .xlsx (el archivo lo arma el backend). */
  reporteExcel(tipo: TipoReporte, filtros: Filtros): Observable<HttpResponse<Blob>> {
    return this.http.get(`${this.url}/reportes/${tipo}/excel`, {
      params: this.params(filtros),
      responseType: 'blob',
      observe: 'response',
    });
  }

  private params(filtros: Filtros): HttpParams {
    let params = new HttpParams();
    for (const [clave, valor] of Object.entries(filtros)) {
      if (valor !== null && valor !== undefined && valor !== '') {
        params = params.set(clave, String(valor));
      }
    }
    return params;
  }
}
