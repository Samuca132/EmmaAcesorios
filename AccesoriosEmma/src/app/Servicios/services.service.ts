import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, Subject } from 'rxjs';
import { Venta } from './ventas';
import { Compras } from './compra';
import { Canje } from './canje';
import { Productos } from './producto';
import { Insumo } from './Insumo';
import { Cliente } from './cliente';
import { Empleado } from './empleado';




@Injectable({
  providedIn: 'root'
})

export class ServicesService {

API: string='http://localhost/BackendEmma/'

  constructor( private clienteHttp:HttpClient) { }

  AgregarTicket(datosVenta:any):Observable<any>{
    return this.clienteHttp.post(this.API+"?insertarTicket=1",datosVenta); 
   }

  AgregarVenta(datosVenta:Venta):Observable<any>{
   return this.clienteHttp.post(this.API+"?insertarVenta=1",datosVenta); 
  }
  
  AgregarCompra(datosVenta:Venta):Observable<any>{
    return this.clienteHttp.post(this.API+"?insertarCompra=1",datosVenta); 
   }
  
   AgregarProducto(datosVenta: any): Observable<any> {
    return this.clienteHttp.post(this.API + "?insertarProducto=1", datosVenta);
  }
  
  AgregarCliente(datosVenta: any): Observable<any> {
    return this.clienteHttp.post(this.API + "?insertarCliente=1", datosVenta);
  }

  AgregarProveedor(datosVenta: any): Observable<any> {
    return this.clienteHttp.post(this.API + "?insertarProveedor=1", datosVenta);
  }

  AgregarCiudad(datosVenta: any): Observable<any> {
    return this.clienteHttp.post(this.API + "?insertarCiudad=1", datosVenta);
  }

  AgregarInsumo(datosVenta: any): Observable<any> {
    return this.clienteHttp.post(this.API + "?insertarInsumo=1", datosVenta);
  }

  AgregarCanje(datosVenta: any): Observable<any> {
    return this.clienteHttp.post(this.API + "?insertarCanje=1", datosVenta);
  }

  ObtenerTickets(){
    return this.clienteHttp.get(this.API+"?listarTicket");
  }

  ObtenerVentas(){
    return this.clienteHttp.get(this.API+"?listarVentas");
  }

  ObtenerProveedores(){
    return this.clienteHttp.get(this.API+"?listarProveedor");
  }

  ObtenerCompras(){
    return this.clienteHttp.get(this.API+"?listarCompras");
  }

  ObtenerProductos(){
    return this.clienteHttp.get(this.API+"?listarProductos");
  }

  ObtenerInsumos(){
    return this.clienteHttp.get(this.API+"?listarInsumos");
  }

  ObtenerClientes(){
    return this.clienteHttp.get(this.API+"?listarClientes");
  }

  ObtenerCiudades(){
    return this.clienteHttp.get(this.API+"?listarCiudades");
  }

  ObtenerCanjes(){
    return this.clienteHttp.get(this.API+"?listarCanjes");
  }
  
  BorrarCompra(id:any):Observable<any>{
    return this.clienteHttp.get(this.API+"?borrar="+id);
   }

  BorrarProducto(id:any):Observable<any>{
    return this.clienteHttp.get(this.API+"?borrarProducto="+id);
   }
  
   BorrarCliente(id:any):Observable<any>{
    return this.clienteHttp.get(this.API+"?borrarCliente="+id);
   }

   BorrarInsumo(id:any):Observable<any>{
    return this.clienteHttp.get(this.API+"?borrarInsumo="+id);
   }

   ObtenerProducto(id:any):Observable<any>{
    return this.clienteHttp.get(this.API+"?consultarProducto="+id);
   }

   ObtenerCliente(id:any):Observable<any>{
    return this.clienteHttp.get(this.API+"?consultarCliente="+id);
   }

   ObtenerVentasTicket(id:any):Observable<any>{
    return this.clienteHttp.get(this.API+"?listarVentasTicket="+id);
   }

   ObtenerInsumo(id:any):Observable<any>{
    return this.clienteHttp.get(this.API+"?consultarInsumo="+id);
   }

   EditarCliente(id:any,datosVenta:any):Observable<any>{
    return this.clienteHttp.post(this.API+"?actualizarCliente="+id,datosVenta);
   }

   EditarInsumo(id:any,datosVenta:any):Observable<any>{
    return this.clienteHttp.post(this.API+"?actualizarInsumo="+id,datosVenta);
   }

   EditarProducto(id:any,datosVenta:any):Observable<any>{
    return this.clienteHttp.post(this.API+"?actualizarProducto="+id,datosVenta);
   }

   LoginPage(user:any):Observable<any>{
    return this.clienteHttp.post(this.API+"?login=", user);
   }


   private tokenSubject = new Subject<string>();

  tokenChanges$ = this.tokenSubject.asObservable();

  setToken(token: string) {
    localStorage.setItem('token', token);
    this.tokenSubject.next(token);
  }

  clearToken() {
    localStorage.removeItem('token');
    this.tokenSubject.next('');
  }

  hasToken() {
    return !!localStorage.getItem('token');
  }
}

