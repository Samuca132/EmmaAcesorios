import { HttpClient, HttpParams } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable } from 'rxjs';
import { environment } from '../../environments/environment';
import {
  Canje, Ciudad, Cliente, Compra, Dashboard, Insumo, Producto, Proveedor, Provincia, Ticket,
} from './models';

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

  registrarCompra(datos: unknown): Observable<Compra> {
    return this.http.post<Compra>(`${this.url}/compras`, datos);
  }

  canjes(): Observable<Canje[]> {
    return this.http.get<Canje[]>(`${this.url}/canjes`);
  }

  registrarCanje(datos: unknown): Observable<Canje> {
    return this.http.post<Canje>(`${this.url}/canjes`, datos);
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
