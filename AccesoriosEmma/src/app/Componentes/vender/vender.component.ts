import { Component, OnInit } from '@angular/core';
import { FormGroup, FormBuilder, FormsModule, ReactiveFormsModule } from '@angular/forms';
import { ActivatedRoute, Router } from '@angular/router';
import { ServicesService } from '../../Servicios/services.service';
import { CommonModule } from '@angular/common';
import { HttpClientModule } from '@angular/common/http';
import { NavbarComponent } from '../navbar/navbar.component';


@Component({
  selector: 'app-vender',
  standalone: true, 
  imports: [FormsModule,ReactiveFormsModule,NavbarComponent,HttpClientModule,CommonModule],
  templateUrl: './vender.component.html',
  styleUrl: './vender.component.css'
})
export class VenderComponent implements OnInit {

  formulario: FormGroup;
  formularios: FormGroup[] = [];
  elID: any;
  clientes: any;
  productos: any;
  PrecioProductoCD: any;
  filteredOptions: string[] = [];
  searchTerm = '';

  constructor(
    private activeRoute: ActivatedRoute,
    private formularioBuilder: FormBuilder,
    private crudService: ServicesService,
    private ruteador: Router
  ) {
    this.elID = this.activeRoute.snapshot.paramMap.get('IDCliente');
    this.crudService.ObtenerProductos().subscribe(respuesta => {
      console.log(respuesta);
      this.productos = respuesta;
    });

    this.formulario = this.formularioBuilder.group({
      IDCliente: [this.elID],
      IDProducto: [''],
      CantidadProducto: [''],
      IDTicket: [''],
    });

    this.filteredOptions = [this.productos];
  }

  ngOnInit(): void {
    console.log("El componente se cargó correctamente");
    this.agregarFormulario();
  }

  agregarFormulario() {
    const nuevoFormulario = this.formularioBuilder.group({
      IDCliente: [this.elID],
      IDProducto: [''],
      CantidadProducto: [''],
      IDTicket: [''],
    });
    this.formularios.push(nuevoFormulario);
  }

  eliminarFormulario(index: number): void {
    this.formularios.splice(index, 1);
  }



  filterOptions() {
    this.filteredOptions = this.productos.filter((producto: any) =>
      producto.toLowerCase().includes(this.searchTerm.toLowerCase())
    );
  }
  

  selectOption(producto: string) {
    console.log(producto);
  }


  concretarVenta() {
    const forms = document.getElementsByTagName('form');
    let activo: boolean = true;
    const productos = forms.length;
    let valores = 0;
  
    for (let i = 0; i < forms.length; i++) {
      const PID = this.formularios[i].value.IDProducto;
      const cantidadProducto = this.formularios[i].value.CantidadProducto;
  
      let VP: any;
  
      this.formularios[i].patchValue({
        IDProducto: this.productos.findIndex((producto: any) => producto.NombreProducto === PID) !== -1
          ? this.productos.find((producto: any) => producto.NombreProducto === PID).IDProducto
          : this.formularios[i].value.IDProducto
      });
  
      const IDP = this.formularios[i].value.IDProducto;
      VP = this.productos.find((producto: any) => producto.IDProducto === IDP)?.PrecioProducto || 0;
  
      VP = VP * cantidadProducto;
      valores += VP;
      console.log(VP);
    }
  
    console.log(valores);
  
    const ticket = {
      CProductos: productos,
      IDCliente: this.elID,
      Valor: valores
    };
  
    let IDTicket = 0;
  
    this.crudService.AgregarTicket(ticket).subscribe((respuesta) => {
      console.log(respuesta);
      IDTicket = respuesta;
  
      for (let i = 0; i < forms.length; i++) {
        const formulario = this.formularios[i];
  
        // Asignar el valor de IDTicket al control correspondiente
        const idTicketControl = formulario.get('IDTicket');
        if (idTicketControl) {
          idTicketControl.setValue(IDTicket);
        }
  
        const idProductoControl = formulario.get('IDProducto');
        const cantidadProductoControl = formulario.get('CantidadProducto');
  
        if (idProductoControl && cantidadProductoControl) {
          const idProducto = idProductoControl.value;
          const cantidadProducto = cantidadProductoControl.value;
  
          if (!idProducto || !cantidadProducto) {
            alert('Complete todos los campos para poder realizar la venta');
            activo = false;
            break;
          }
        }
      }
  
      if (activo) {
        for (let i = 0; i < forms.length; i++) {
          const formulario = this.formularios[i];
  
          const PID = formulario.value.IDProducto;
          formulario.patchValue({
            IDProducto: this.productos.findIndex((producto: any) => producto.NombreProducto === PID) !== -1
              ? this.productos.find((producto: any) => producto.NombreProducto === PID).IDProducto
              : formulario.value.IDProducto
          });
  
          this.crudService.AgregarVenta(formulario.value).subscribe(() => {
            console.log('Venta registrada');
          });
        }
  
        this.ruteador.navigateByUrl('/Profile/' + this.elID);
      }
    });
  
    console.log(ticket);
    console.log(IDTicket);
  }
  


}
