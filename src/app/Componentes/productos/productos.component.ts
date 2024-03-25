import { Component,OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ServicesService } from '../../Servicios/services.service';
import { FormsModule } from '@angular/forms';
import { NavbarComponent } from '../navbar/navbar.component';

@Component({
  selector: 'app-productos',
  standalone: true,
  imports: [CommonModule,FormsModule,NavbarComponent],
  templateUrl: './productos.component.html',
  styleUrl: './productos.component.css'
})
export class ProductosComponent implements OnInit {
  productos: any;
  filtroNombre: string = '';
  productosFiltrados: any;

  constructor(private Service: ServicesService) {}

  ngOnInit(): void {
    this.Service.ObtenerProductos().subscribe(respuesta => {
      console.log(respuesta);
      this.productos = respuesta;
    });
  }

  borrarRegistro(id: any, iControl: any) {
    console.log(id);
    console.log(iControl);
    if (confirm('¿Estás seguro de que quieres borrar este producto?')) {
      this.Service.BorrarProducto(id).subscribe((respuesta) => (
        this.productos.splice(iControl, 1)
      ));
    }
  }

    filtrarProductos(event: any) {
      const filtro = event.target.value.toLowerCase();
      this.Service.ObtenerProductos().subscribe(respuesta => {
        this.productosFiltrados = Object.values(respuesta).filter((producto: any) => {
          return producto.NombreProducto.toLowerCase().includes(filtro);
        });
        this.productos=this.productosFiltrados;
      });
    }
    
}
