import { Component,OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ServicesService } from '../../Servicios/services.service';
import { NavbarComponent } from '../navbar/navbar.component';

@Component({
  selector: 'app-canjes',
  standalone: true,
  imports: [CommonModule,NavbarComponent],
  templateUrl: './canjes.component.html',
  styleUrl: './canjes.component.css'
})
export class CanjesComponent implements OnInit {
  Canjes:any;

  Proveedores:any;
  productos:any;
  ciudades:any;
  insumos:any;

  CanjesFiltradas: any;
  CanjesOriginales: any;
  proveedorSeleccionado:any;
  ProveedoresFiltrados:any;
  
  mostrarBotonNuevoCanje: boolean = true;
  filtrarPorProveedor: boolean = false;
  filtrarPorProducto: boolean = false;
  filtrarPorCiudad: boolean = false;
  filtrarPorInsumo: boolean = false;

  constructor(
    private Service:ServicesService
    ){}

  ngOnInit(): void {
    console.log('esto funciona');
    this.Service.ObtenerCanjes().subscribe(respuesta=>{console.log(respuesta);
      this.Canjes=respuesta; 
      this.CanjesOriginales = respuesta; 
      });

    this.Service.ObtenerProveedores().subscribe(respuesta => {
      console.log('Proveedores');
      this.Proveedores=respuesta;
      console.log(this.Proveedores)
      });

    this.Service.ObtenerProductos().subscribe(respuesta=>{this.productos=respuesta;
    console.log(this.productos)})
    
    this.Service.ObtenerCiudades().subscribe(respuesta=>{this.ciudades=respuesta;
      console.log(this.ciudades)})

    this.Service.ObtenerInsumos().subscribe(respuesta=>{this.insumos=respuesta;
      console.log(this.insumos)})
  }

  selectFilter(event: any): void {
    if (event && event.target && event.target.value) {
      const Select = event.target.value;
     switch(Select){
      case '0':
        this.mostrarBotonNuevoCanje = true; 
        this.filtrarPorProveedor=false;
        this.filtrarPorProducto=false
        this.filtrarPorCiudad=false
        this.filtrarPorInsumo=false;
        this.Canjes = this.CanjesOriginales;
      break;
  
      case '1':
        this.mostrarBotonNuevoCanje = false; 
        this.filtrarPorProveedor=true;
        this.filtrarPorProducto=false;
        this.filtrarPorCiudad=false
        this.filtrarPorInsumo=false;
        this.Canjes = this.CanjesOriginales;
      break;
  
      case '2':
        this.mostrarBotonNuevoCanje = false; 
        this.filtrarPorProveedor=false;
        this.filtrarPorProducto=true;
        this.filtrarPorCiudad=false
        this.filtrarPorInsumo=false;
        this.Canjes = this.CanjesOriginales;
      break;
  
      case '3':
        this.mostrarBotonNuevoCanje = false; 
        this.filtrarPorProveedor=false;
        this.filtrarPorProducto=false;
        this.filtrarPorCiudad=true;
        this.filtrarPorInsumo=false;
        this.Canjes = this.CanjesOriginales;
      break;

      case '4':
        this.mostrarBotonNuevoCanje = false; 
        this.filtrarPorProveedor=false;
        this.filtrarPorProducto=false;
        this.filtrarPorCiudad=false;
        this.filtrarPorInsumo=true;
        this.Canjes = this.CanjesOriginales;
      break;
  
     }
    }
  }

  filtrarCanjesPorProveedor(event: any): void { 
    if (event && event.target && event.target.value) {
      const IDProveedor = event.target.value;
      if(IDProveedor==0){
        this.Canjes = this.CanjesOriginales;
       
      }else{
        this.CanjesFiltradas = this.CanjesOriginales.filter((venta: { IDProveedor: number }) => venta.IDProveedor === IDProveedor);
        this.Canjes = this.CanjesFiltradas;
  
      }
  
    }
  }
  
  filtrarCanjesPorProducto(event: any): void {
    if (event && event.target && event.target.value) {
      const IDProducto = event.target.value;
      if(IDProducto==0){
        this.Canjes = this.CanjesOriginales;
      }else{
        this.CanjesFiltradas = this.CanjesOriginales.filter((venta: { IDProducto: number }) => venta.IDProducto === IDProducto);
        this.Canjes = this.CanjesFiltradas;
      }
  
    }
  }
  
  filtrarCanjesPorCiudad(event: any): void {
    if (event && event.target && event.target.value) {
      const IDCiudad = event.target.value;
      if (IDCiudad == 0) {
        this.Canjes = this.CanjesOriginales;
      } else {
        this.ProveedoresFiltrados = this.Proveedores.filter((Proveedor: { IDCiudad: number }) => Proveedor.IDCiudad === IDCiudad);
        const IDsProveedoresFiltrados = this.ProveedoresFiltrados.map((Proveedor: { IDProveedor: number }) => Proveedor.IDProveedor);
        this.Canjes = this.CanjesOriginales.filter((venta: { IDProveedor: number }) => IDsProveedoresFiltrados.includes(venta.IDProveedor));
      }
    }
  }
}
