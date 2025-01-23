import { Component,OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ServicesService } from '../../Servicios/services.service';
import { NavbarComponent } from '../navbar/navbar.component';
import { jsPDF } from 'jspdf';

@Component({
  selector: 'app-ventas',
  standalone: true,
  imports: [CommonModule,NavbarComponent],
  templateUrl: './ventas.component.html',
  styleUrl: './ventas.component.css'
})
export class VentasComponent implements OnInit{
  Ventas:any;
  Tickets:any;
  clientes:any;
  productos:any;
  ciudades:any;
  ventasFiltradas: any;
  ventasAgrupadas:any;
  clientesFiltrados: any;
  ventasOriginales: any;
  clienteSeleccionado:any;
  mostrarBotonNuevaVenta: boolean = true;
  filtrarPorCliente: boolean = false;
  filtrarPorProducto: boolean = false;
  filtrarPorCiudad: boolean = false;
  elID: any;

  constructor(
  private Service:ServicesService
  ){}
 
  ngOnInit(): void {
    console.log("esto funciona")
    this.Service.ObtenerTickets().subscribe(respuesta=>{console.log(respuesta);
    this.Ventas=respuesta; 
    this.ventasOriginales = respuesta;     
    });

    this.Service.ObtenerTickets().subscribe(respuesta=>{console.log(respuesta);
      this.Tickets=respuesta;   
      console.log(this.Tickets)
      });

    this.Service.ObtenerClientes().subscribe(respuesta => {
      this.clientes=respuesta;
      });
    this.Service.ObtenerProductos().subscribe(respuesta=>{this.productos=respuesta;
    console.log(this.productos)})
    this.Service.ObtenerCiudades().subscribe(respuesta=>{this.ciudades=respuesta;
      console.log(this.ciudades)})
    
}
// this.Ventas.filter((venta: { IDCliente: number }) => venta.IDCliente === IDCliente);
/* 

for (const venta of this.Ventas) {
      const fecha = venta.fechaVenta;

      if (!this.ventasAgrupadas[fecha]) {
        this.ventasAgrupadas[fecha] = [];
      }  
      // Añadimos la venta al grupo correspondiente a esa fecha
      this.ventasAgrupadas[fecha].push(venta);
    }
    this.Ventas=this.ventasAgrupadas
------------------------------------------------
this.mostrarBotonNuevaVenta = true; 
      this.filtrarPorCliente=false;
      this.filtrarPorProducto=false */
selectFilter(event: any): void {
  if (event && event.target && event.target.value) {
    const Select = event.target.value;
   switch(Select){
    case '0':
      this.mostrarBotonNuevaVenta = true; 
      this.filtrarPorCliente=false;
      this.filtrarPorProducto=false
      this.filtrarPorCiudad=false
      this.Ventas = this.ventasOriginales;
    break;

    case '1':
      this.mostrarBotonNuevaVenta = false; 
      this.filtrarPorCliente=true;
      this.filtrarPorProducto=false;
      this.filtrarPorCiudad=false
      this.Ventas = this.ventasOriginales;
    break;

    case '2':
      this.mostrarBotonNuevaVenta = false; 
      this.filtrarPorCliente=false;
      this.filtrarPorProducto=true;
      this.filtrarPorCiudad=false
      this.Ventas = this.ventasOriginales;
    break;

    case '3':
      this.mostrarBotonNuevaVenta = false; 
      this.filtrarPorCliente=false;
      this.filtrarPorProducto=false;
      this.filtrarPorCiudad=true;
      this.Ventas = this.ventasOriginales;
    break;

   }
  }
}

filtrarVentasPorCliente(event: any): void { 
  if (event && event.target && event.target.value) {
    const IDCliente = event.target.value;
    if(IDCliente==0){
      this.Ventas = this.ventasOriginales;
     
    }else{
      this.ventasFiltradas = this.ventasOriginales.filter((venta: { IDCliente: number }) => venta.IDCliente === IDCliente);
      this.Ventas = this.ventasFiltradas;

    }

  }
}

filtrarVentasPorProducto(event: any): void {
  if (event && event.target && event.target.value) {
    const IDProducto = event.target.value;
    if(IDProducto==0){
      this.Ventas = this.ventasOriginales;
    }else{
      this.ventasFiltradas = this.ventasOriginales.filter((venta: { IDProducto: number }) => venta.IDProducto === IDProducto);
      this.Ventas = this.ventasFiltradas;
    }

  }
}

filtrarVentasPorCiudad(event: any): void {
  if (event && event.target && event.target.value) {
    const IDCiudad = event.target.value;
    if (IDCiudad == 0) {
      this.Ventas = this.ventasOriginales;
    } else {
      this.clientesFiltrados = this.clientes.filter((cliente: { IDCiudad: number }) => cliente.IDCiudad === IDCiudad);
      const IDsClientesFiltrados = this.clientesFiltrados.map((cliente: { IDCliente: number }) => cliente.IDCliente);
      this.Ventas = this.ventasOriginales.filter((venta: { IDCliente: number }) => IDsClientesFiltrados.includes(venta.IDCliente));
    }
  }
}

filtrarClientes(event: any) {
  const filtro = event.target.value.toLowerCase();
  this.Service.ObtenerClientes().subscribe(respuesta => {
    this.clientesFiltrados = Object.values(respuesta).filter((cliente: any) => {
      return cliente.nombreCliente.toLowerCase().includes(filtro);
    });
   this.clientes=this.clientesFiltrados;
  });
}
    


generarPDF() {
  const doc = new jsPDF();

  doc.text('Hola, este es un PDF generado en Angular!', 10, 10);
  // Guardar el archivo
  doc.save('documento.pdf');
}
}
