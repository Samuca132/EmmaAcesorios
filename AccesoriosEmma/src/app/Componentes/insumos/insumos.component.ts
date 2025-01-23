import { Component,OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ServicesService } from '../../Servicios/services.service';
import { NavbarComponent } from '../navbar/navbar.component';

@Component({
  selector: 'app-insumos',
  standalone: true,
  imports: [CommonModule,NavbarComponent],
  templateUrl: './insumos.component.html',
  styleUrl: './insumos.component.css' 
})
export class InsumosComponent implements OnInit {
  insumos:any;
  InsumosFiltrados:any;

  constructor(
  private Service:ServicesService
  ){}
  ngOnInit(): void {
    console.log("esto funciona")
    this.Service.ObtenerInsumos().subscribe(respuesta=>{console.log(respuesta);
    this.insumos=respuesta; 
    });
    
}
borrarRegistro(id:any, iControl:any){
  console.log(id);
  console.log(iControl);
  if (confirm('¿Estás seguro de que quieres borrar este producto?')) {
  this.Service.BorrarInsumo(id).subscribe((respuesta)=>(
    this.insumos.splice(iControl,1)
  ));
  }

}
filtrarInsumos(event: any) {
  const filtro = event.target.value.toLowerCase();
  this.Service.ObtenerInsumos().subscribe(respuesta => {
    this.InsumosFiltrados = Object.values(respuesta).filter((insumo: any) => {
      return insumo.NombreInsumo.toLowerCase().includes(filtro);
    });
    console.log(this.InsumosFiltrados)
   this.insumos=this.InsumosFiltrados;
  });
}
}
