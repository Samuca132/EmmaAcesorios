import { Component,OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ServicesService } from '../../Servicios/services.service';
import { NavbarComponent } from '../navbar/navbar.component';


@Component({
  selector: 'app-compras',
  standalone: true,
  imports: [CommonModule,NavbarComponent],
  templateUrl: './compras.component.html',
  styleUrl: './compras.component.css'
})
export class ComprasComponent implements OnInit{
  compras:any;
  comprasOriginales:any;
  comprasFiltradas:any;
  proveedores:any;
  insumos:any;
  mostrarBotonNuevaCompra: boolean = true;
  filtrarPorProveedor: boolean = false;
  filtrarPorInsumo: boolean = false;

  constructor(
  private Service:ServicesService
  ){}
 
  ngOnInit(): void {
    console.log("esto funciona")
    this.Service.ObtenerCompras().subscribe(respuesta=>{console.log(respuesta);
    this.compras=respuesta;
    this.comprasOriginales = respuesta;  
    });   
    this.Service.ObtenerProveedores().subscribe(respuesta=>{console.log(respuesta);
    this.proveedores=respuesta;
    }); 
    this.Service.ObtenerInsumos().subscribe(respuesta=>{console.log(respuesta);
    this.insumos=respuesta;
    });   
}

selectFilter(event: any): void {
  if (event && event.target && event.target.value) {
    const Select = event.target.value;
   switch(Select){
    case '0':
      this.mostrarBotonNuevaCompra = true;
      this.filtrarPorProveedor= false;
      this.filtrarPorInsumo= false;
      this.compras=this.comprasOriginales;
    break;
    case '1':
      this.mostrarBotonNuevaCompra = false;
      this.filtrarPorProveedor= true;
      this.filtrarPorInsumo= false;
      this.compras=this.comprasOriginales;
    break;
    case '2':
      this.mostrarBotonNuevaCompra = false;
      this.filtrarPorProveedor= false;
      this.filtrarPorInsumo= true;
      this.compras=this.comprasOriginales;
    break;
   }
  }
}

filtrarVentasPorProveedor(event: any): void {
  if (event && event.target && event.target.value) {
    const IDProveedor = event.target.value;
    if(IDProveedor==0){
      this.compras = this.comprasOriginales;
    }else{
      this.comprasFiltradas = this.comprasOriginales.filter((compra: { IDProveedor: number }) => compra.IDProveedor === IDProveedor);
      this.compras = this.comprasFiltradas;
    }

  }
}
filtrarVentasPorInsumo(event: any): void {
  if (event && event.target && event.target.value) {
    const IDInsumo = event.target.value;
    if(IDInsumo==0){
      this.compras = this.comprasOriginales;
    }else{
      this.comprasFiltradas = this.comprasOriginales.filter((compra: { IDInsumo: number }) => compra.IDInsumo === IDInsumo);
      this.compras = this.comprasFiltradas;
    }

  }
}
}