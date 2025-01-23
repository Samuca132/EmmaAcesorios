import { Component,OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ServicesService } from '../../Servicios/services.service';
import { NavbarComponent } from '../navbar/navbar.component';

@Component({
  selector: 'app-clientes',
  standalone: true,
  imports: [CommonModule,NavbarComponent],
  templateUrl: './clientes.component.html',
  styleUrl: './clientes.component.css'
})
export class ClientesComponent implements OnInit {
  clientes:any;
  clientesFiltrados:any;

  constructor(
  private Service:ServicesService
  ){}
  ngOnInit(): void {
    console.log("esto funciona")
    this.Service.ObtenerClientes().subscribe(respuesta=>{console.log(respuesta);
    this.clientes=respuesta; 
    });
    
} 
borrarRegistro(id: any, iControl: any) {
  console.log(id);
  console.log(iControl);
  if (confirm('¿Estás seguro de que quieres borrar este producto?')) {
    this.Service.BorrarCliente(id).subscribe((respuesta) => (
      this.clientes.splice(iControl, 1)
    ));
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

}
