import { Component,OnInit } from '@angular/core';

import { CommonModule } from '@angular/common';
import { Router, ActivatedRoute } from '@angular/router';
import { FormGroup,FormBuilder,FormsModule,ReactiveFormsModule } from '@angular/forms';
import { ServicesService } from '../../Servicios/services.service';
import compress from 'browser-image-compression';
import { NavbarComponent } from '../navbar/navbar.component';


@Component({
  selector: 'app-editar',
  standalone: true,
  imports: [ CommonModule,FormsModule,ReactiveFormsModule,NavbarComponent],
  templateUrl: './editar.component.html',
  styleUrl: './editar.component.css'
})
export class EditarComponent implements OnInit {
  FormularioEditar: FormGroup;
  elID: any;
  selectedFile: string="";
  opcion: any;
  identifier:any;
  ciudades:any;
  ciudadSeleccionada: any;

  constructor(
    private activeRoute: ActivatedRoute,
    private crudService: ServicesService,
    public formulario: FormBuilder,
    private ruteador: Router
  ) {
    this.elID = this.activeRoute.snapshot.paramMap.get('id');
    this.opcion = this.activeRoute.snapshot.paramMap.get('tabla');
    
    this.FormularioEditar = this.formulario.group({});

    switch (this.opcion) {
      case "producto":
        this.identifier="IDProducto"
        this.selectedFile = "assets/products/"
        this.crudService.ObtenerProducto(this.elID).subscribe(
          respuesta => {
            this.FormularioEditar.patchValue({
              nombre: respuesta[0]['NombreProducto'],
              stock: respuesta[0]['stockProducto'],
              precio: respuesta[0]['PrecioProducto'],
              coste: respuesta[0]['costeProduccion'],
              imagen: respuesta[0]['ImagenProducto'],
            });
            this.selectedFile = respuesta[0]['ImagenProducto'];
            console.log(this.selectedFile) // Para mostrar la imagen previamente seleccionada
          }
        );
    
        this.FormularioEditar = this.formulario.group({
          nombre: [''],
          stock: [''],
          precio: [''],
          coste: [''],
          imagen: ['']
        });
        console.log("Opción 1 seleccionada");
        break;


      case "cliente":

        this.identifier="IDCliente"
        this.selectedFile = "assets/clients/"
        this.crudService.ObtenerCliente(this.elID).subscribe(
          respuesta => {
            this.FormularioEditar.patchValue({
              nombre: respuesta[0]['nombreCliente'],
              IDCiudad: respuesta[0]['IDCiudad'],
              imagen: respuesta[0]['Imagen'],
              TelefonoCliente: respuesta[0]['telefonoCliente'],
            });
            this.selectedFile = respuesta[0]['Imagen'];
            console.log(respuesta);
            
            const IDCiudadCliente = respuesta[0]['IDCiudad'];
            this.crudService.ObtenerCiudades().subscribe(respuestaCiudades => {
              console.log(respuestaCiudades);
              const ciudadesArray = Object.values(respuestaCiudades);
              this.ciudades = ciudadesArray.filter((ciudad: any) => ciudad.IDCiudad === IDCiudadCliente);
              console.log(ciudadesArray);
              ciudadesArray.forEach((ciudad: any) => {
                if (ciudad.IDCiudad !== IDCiudadCliente) {
                  this.ciudades.push(ciudad);
                }
              });
              this.FormularioEditar.patchValue({
                ciudad: this.ciudades.length > 0 ? this.ciudades[0].IDCiudad : null
              });
            });
          }
        );
        
 
        this.FormularioEditar = this.formulario.group({
          nombre: [''],
          imagen:[''],
          IDCiudad:[''],
          TelefonoCliente:[''],
        });


        
        console.log("Opción 2 seleccionada");
        break;
        case "insumo":
          this.identifier="IDInsumo"

          this.crudService.ObtenerInsumo(this.elID).subscribe(
            respuesta => {
              this.FormularioEditar.patchValue({
                nombre: respuesta[0]['NombreInsumo'],
                stock: respuesta[0]['Stock'], 
                precio: respuesta[0]['precio'],
                dscnt: respuesta[0]['DescuentoPactadoCanje'],
                imagen: respuesta[0]['ImagenInsumo']
              });
              
              //this.selectedFile = "data:image/jpeg;base64," + respuesta[0]['ImagenInsumo'];
              this.selectedFile = respuesta[0]['ImagenInsumo'];
              console.log(this.selectedFile) // Para mostrar la imagen previamente seleccionada
            }
          );

          this.FormularioEditar = this.formulario.group({
            nombre: [''],
            stock: [''],
            dscnt:[''],
            precio: [''],
            imagen:['']
          });
          console.log("Opción 3 seleccionada");
        break;
            
      default:
        console.log("Opción no reconocida");
    }

  
  }



  enviarDatos(): any {
    console.log(this.elID);
    console.log(this.FormularioEditar.value);
    switch (this.opcion) {
      case "producto":
        this.crudService.EditarProducto(this.elID,this.FormularioEditar.value).subscribe(()=>{});
        alert('Edición de Producto correcta')
        this.ruteador.navigateByUrl('/Productos');
        break;

      case "cliente":
        this.crudService.EditarCliente(this.elID,this.FormularioEditar.value).subscribe(()=>{});
        alert('Edición de CLiente correcta')
        this.ruteador.navigateByUrl('/Clientes');
        console.log("Cliente")
        break;

      case "insumo":
        this.crudService.EditarInsumo(this.elID,this.FormularioEditar.value).subscribe(()=>{});
        alert('Edición de Insumo correcta')
        this.ruteador.navigateByUrl('/Insumos');
        break;
    }
    //this.crudService.EditarEmpleado(this.elID,this.formularioEmpleados.value).subscribe(()=>{});
    //this.ruteador.navigateByUrl('/listar-empleado')
  }
  

  
  bloquearComponente: boolean = false;

  onFileChange(event: any): void {
    this.bloquearComponente = true; // Bloquear el componente
  
    const file: File = event.target.files[0];
    if (file) {
      const options = {
        maxSizeMB: 0.5, // Tamaño máximo en MB
        maxWidthOrHeight: 1920, // Ancho o alto máximo
        useWebWorker: true 
      }
  
      compress(file, options).then((compressedBlob: Blob) => {
        const reader = new FileReader();
        reader.onload = (e: any) => {
          const base64Image = e.target.result;
          console.log('Base64:', base64Image);
          this.FormularioEditar.patchValue({imagen: base64Image});
          this.selectedFile = base64Image; // Actualizar la vista previa
          this.bloquearComponente = false; // Desbloquear el componente cuando termine
        };
        reader.readAsDataURL(compressedBlob);
      });
    }
  }

  ngOnInit(): void {
      
  }
}





