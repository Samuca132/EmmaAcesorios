import { Component,OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { Router, ActivatedRoute } from '@angular/router';
import { FormGroup,FormBuilder,FormsModule,ReactiveFormsModule } from '@angular/forms';
import { ServicesService } from '../../Servicios/services.service';
import compress from 'browser-image-compression';


import { HttpClientModule  } from '@angular/common/http';
import { NavbarComponent } from '../navbar/navbar.component';
@Component({
  selector: 'app-add',
  standalone: true, 
  imports: [FormsModule,ReactiveFormsModule,NavbarComponent,HttpClientModule,CommonModule],
  templateUrl: './add.component.html',
  styleUrl: './add.component.css'
})
export class AddComponent implements OnInit {
  formularioMultiuso:FormGroup;
  opcion: any;
  clientes:any;
  proveedores:any;
  ciudades:any;
  insumos:any;
  productos:any;
  descuento:any;
  PrecioInsumoCD:any;
  PrecioProductoCD:any;

  idProveedorControl:any;
  idInsumoControl:any;
  cantidadInsumooControl:any;
  idProductoControl:any;
  cantidadProductoControl:any;

  selectedFile:string = "/assets/picture.png";

  provinciasArgentinas = [
    { id: 1, nombre: "Córdoba" },
    { id: 2, nombre: "Buenos Aires" },
    { id: 3, nombre: "Santa Fe" },
    { id: 4, nombre: "Mendoza" },
    { id: 5, nombre: "Tucumán" },
    { id: 6, nombre: "Entre Ríos" },
    { id: 7, nombre: "Salta" },
    { id: 8, nombre: "Chaco" },
    { id: 9, nombre: "Corrientes" },
    { id: 10, nombre: "Santiago del Estero" },
    { id: 11, nombre: "San Juan" },
    { id: 12, nombre: "Jujuy" },
    { id: 13, nombre: "Río Negro" },
    { id: 14, nombre: "Neuquén" },
    { id: 15, nombre: "Formosa" },
    { id: 16, nombre: "Chubut" },
    { id: 17, nombre: "San Luis" },
    { id: 18, nombre: "La Pampa" },
    { id: 19, nombre: "La Rioja" },
    { id: 20, nombre: "Santa Cruz" },
    { id: 21, nombre: "Tierra del Fuego" }
  ];
  
  constructor( 
    private activeRoute: ActivatedRoute,
    public formulario:FormBuilder,
    private crudService:ServicesService,
    private ruteador:Router
    ) {

    this.formularioMultiuso=this.formulario.group({});

    this.opcion = this.activeRoute.snapshot.paramMap.get('tabla');
    switch (this.opcion) {
      
      case "venta":
        this.formularioMultiuso=this.formulario.group({
          IDCliente:[''],
          IDProducto:[''],
          CantidadProducto:[''],
        });

      this.crudService.ObtenerClientes().subscribe(respuesta => {
        console.log(respuesta);
        this.clientes=respuesta;
        });
      this.crudService.ObtenerProductos().subscribe(respuesta => {
      console.log(respuesta);
      this.productos=respuesta;
      });


      break;

      case "compra": 
        this.formularioMultiuso=this.formulario.group({
            IDProveedor:[''],
            IDInsumo:[''],
            CantidadCompra:[''],
          });
        
        this.crudService.ObtenerProveedores().subscribe(respuesta => {
          console.log(respuesta);
          this.proveedores=respuesta;
          });

          this.crudService.ObtenerInsumos().subscribe(respuesta => {
            console.log(respuesta);
            this.insumos=respuesta;
            });
      break;

      case "canje": 
      this.formularioMultiuso=this.formulario.group({
        IDProveedor:[''],
        IDInsumo:[''],
        CantidadInsumo:[''],
        IDProducto:[''],
        CantidadProducto:[''],
        DescuentoProducto:[''],
        DescuentoInsumo:[''],
        Profit:[''],
      });

      this.crudService.ObtenerProveedores().subscribe(respuesta => {
        console.log(respuesta);
        this.proveedores=respuesta;
        });

      this.crudService.ObtenerInsumos().subscribe(respuesta => {
        console.log(respuesta);
        this.insumos=respuesta;
        });

      this.crudService.ObtenerProductos().subscribe(respuesta => {
        console.log(respuesta);
        this.productos=respuesta;
        });
      break;

      case "producto":      
      this.formularioMultiuso=this.formulario.group({
        NombreProducto:[''],
        CosteProduccion:[''],
        PrecioProducto:[''],
        imagen:[File],
      });
 
      break;

      case "insumo": 
        this.formularioMultiuso=this.formulario.group({
          NombreInsumo:[''],
          precio:[''],
          DescuentoPactadoCanje:[''],
          imagen:[File],
        });
      break;

      case "cliente": 
        this.formularioMultiuso=this.formulario.group({
          nombreCliente:[''],
          IDCiudad:[''],
          telefonoCliente:[''],
          imagen:[File],
        });
        
        this.crudService.ObtenerCiudades().subscribe(respuesta => {
          console.log(respuesta);
          this.ciudades=respuesta;
          });
      break;
      case "proveedor": 
      this.formularioMultiuso=this.formulario.group({
        nombreProveedor:[''],
        IDCiudad:[''],
        telefonoProveedor:[''],
      });
      
      this.crudService.ObtenerCiudades().subscribe(respuesta => {
        console.log(respuesta);
        this.ciudades=respuesta;
        });
    break;
    
    case "ciudad": 
    this.formularioMultiuso=this.formulario.group({
      nombreCiudad:[''],
      IDProvincia:[''],
    });
   
  break;
    }


  }

  enviarDatos(): any {
    
    console.log(this.formularioMultiuso.value);
    
    //this.crudService.AgregarVenta(this.formularioMultiuso.value).subscribe();
    switch (this.opcion) {
      case "venta": 

          const idClienteControl = this.formularioMultiuso.get('IDCliente');
          const idProductoControl = this.formularioMultiuso.get('IDProducto');
          const cantidadProductoControl = this.formularioMultiuso.get('CantidadProducto');

          if (idClienteControl && idProductoControl && cantidadProductoControl) {
            const idCliente = idClienteControl.value;
            const idProducto = idProductoControl.value;
            const cantidadProducto = cantidadProductoControl.value;

            if (!idCliente || !idProducto || !cantidadProducto) {
              alert('Complete todos los campos para poder realizar la venta');
            } else {
              alert('Venta exitosa');
              this.crudService.AgregarVenta(this.formularioMultiuso.value).subscribe();
              this.ruteador.navigateByUrl('/Ventas')
            }
          }
      break;
      case "canje": 
          this.idProveedorControl = this.formularioMultiuso.get('IDProveedor');
          this.idInsumoControl = this.formularioMultiuso.get('IDInsumo');
          this.cantidadInsumooControl = this.formularioMultiuso.get('CantidadInsumo');
          this.idProductoControl=this.formularioMultiuso.get('IDProducto');
          this.cantidadProductoControl = this.formularioMultiuso.get('CantidadProducto');
          
          if (this.idProveedorControl && this.idInsumoControl && this.cantidadInsumooControl
            &&this.idProductoControl && this.cantidadInsumooControl) {
            const idProveedor = this.idProveedorControl.value;
            const idInsumo = this.idInsumoControl.value;
            const cantidadInsumo = this.cantidadInsumooControl.value;
            const idProducto = this.idProductoControl.value;
            const cantidadProducto = this.cantidadProductoControl.value;

            const profit= this.Profit(idInsumo,idProducto);
            if (!idProveedor || !idInsumo || !cantidadInsumo || !idProducto || !cantidadProducto) {
              alert('Complete todos los campos para poder realizar el canje');
              console.log(this.formularioMultiuso);
            } else {
               
              this.formularioMultiuso.value.IDProveedor = this.proveedores.findIndex((proveedor:any) => proveedor.nombre === this.formularioMultiuso.value.IDProveedor) !== -1 
              ? this.proveedores.find((proveedor:any) => proveedor.nombre === this.formularioMultiuso.value.IDProveedor).IDProveedor : this.formularioMultiuso.value.IDProveedor;
              
              this.formularioMultiuso.value.IDInsumo = this.insumos.findIndex((insumo:any) => insumo.nombre === this.formularioMultiuso.value.IDInsumo) !== -1 
              ? this.insumos.find((insumo:any) => insumo.nombre === this.formularioMultiuso.value.IDInsumo).IDInsumo : this.formularioMultiuso.value.IDInsumo;

              this.formularioMultiuso.value.IDProducto = this.productos.findIndex((producto:any) => producto.NombreProducto === this.formularioMultiuso.value.IDProducto) !== -1
              ? this.productos.find((producto:any) => producto.NombreProducto === this.formularioMultiuso.value.IDProducto).IDProducto
              : this.formularioMultiuso.value.IDProducto;

              this.formularioMultiuso.get('Profit')?.setValue(profit);
              //alert( this.formularioMultiuso.get('Profit')?.value)
              this.crudService.AgregarCanje(this.formularioMultiuso.value).subscribe();
              alert('Compra exitosa');
              console.log(this.formularioMultiuso)
              this.ruteador.navigateByUrl('/Canjes')
            }
          }
      break;

      case "compra": 
      this.idProveedorControl = this.formularioMultiuso.get('IDProveedor');
      this.idInsumoControl = this.formularioMultiuso.get('IDInsumo');
      this.cantidadInsumooControl = this.formularioMultiuso.get('CantidadProducto');
      
      if (this.idProveedorControl && this.idInsumoControl && this.cantidadInsumooControl) {
        const idProveedor = this.idProveedorControl.value;
        const idInsumo = this.idInsumoControl.value;
        const cantidadInsumo = this.cantidadInsumooControl.value;

        if (!idProveedor || !idInsumo || !cantidadInsumo) {
          this.formularioMultiuso.value.IDProveedor = this.proveedores.findIndex((proveedor:any) => proveedor.nombre === this.formularioMultiuso.value.IDProveedor) !== -1 
          ? this.proveedores.find((proveedor:any) => proveedor.nombre === this.formularioMultiuso.value.IDProveedor).IDProveedor : this.formularioMultiuso.value.IDProveedor;


          alert('Complete todos los campos para poder realizar la compra');
        } else {

          this.formularioMultiuso.value.IDProveedor = this.proveedores.findIndex((proveedor:any) => proveedor.nombre === this.formularioMultiuso.value.IDProveedor) !== -1 
          ? this.proveedores.find((proveedor:any) => proveedor.nombre === this.formularioMultiuso.value.IDProveedor).IDProveedor : this.formularioMultiuso.value.IDProveedor;

          this.formularioMultiuso.value.IDInsumo = this.insumos.findIndex((insumo:any) => insumo.nombre === this.formularioMultiuso.value.IDInsumo) !== -1 
          ? this.insumos.find((insumo:any) => insumo.nombre === this.formularioMultiuso.value.IDInsumo).IDInsumo : this.formularioMultiuso.value.IDInsumo;

          this.crudService.AgregarCompra(this.formularioMultiuso.value).subscribe();
          alert('Compra exitosa');
          this.ruteador.navigateByUrl('/Compras')
        }
      }  

  break;
      case "producto":
        const NombreProductoControl = this.formularioMultiuso.get('NombreProducto');
        const CosteProduccionControl = this.formularioMultiuso.get('CosteProduccion');
        const PrecioProductoControl = this.formularioMultiuso.get('PrecioProducto');
        const ImagenProductoControl = this.formularioMultiuso.get('imagen');

        if(NombreProductoControl&&CosteProduccionControl
          &&PrecioProductoControl&&ImagenProductoControl){
          const NombreProducto=NombreProductoControl.value;
          const CosteProduccion=CosteProduccionControl.value;
          const PrecioProducto=PrecioProductoControl.value;
          const ImagenProducto=ImagenProductoControl.value;

          if (!NombreProducto||!CosteProduccion||!PrecioProducto||!ImagenProducto){
            alert('Complete todos los campos');
          }else{
            this.crudService.AgregarProducto(this.formularioMultiuso.value).subscribe();
            alert('Producto agregado');
            this.ruteador.navigateByUrl('/Productos');
          }
        }
       
      break; 
      
      case "insumo":

        const NombreInsumoControl = this.formularioMultiuso.get('NombreInsumo');
        const precioControl = this.formularioMultiuso.get('precio');
        const DescuentoControl = this.formularioMultiuso.get('DescuentoPactadoCanje');
        const ImagenInsumoControl = this.formularioMultiuso.get('imagen');

        if(NombreInsumoControl&&precioControl
          &&DescuentoControl&&ImagenInsumoControl){

            const NombreInsumo=NombreInsumoControl.value;
            const precio=precioControl.value;
            const Descuento=DescuentoControl.value;
            const ImagenInsumo=ImagenInsumoControl.value; 

            if (!NombreInsumo||!precio||!Descuento||!ImagenInsumo){
              alert('Complete todos los campos');
            }else{
              this.crudService.AgregarInsumo(this.formularioMultiuso.value).subscribe();
              alert('Insumo agregado');
              this.ruteador.navigateByUrl('/Insumos')
          }
          }

      break; 
      case "cliente":
        const nombreClienteControl = this.formularioMultiuso.get('nombreCliente');
        const IDCiudadControl = this.formularioMultiuso.get('IDCiudad');
        const telefonoClienteControl = this.formularioMultiuso.get('telefonoCliente');
        const ImagenClienteControl = this.formularioMultiuso.get('imagen');

        if(nombreClienteControl&&IDCiudadControl
          &&telefonoClienteControl&&ImagenClienteControl){

            const nombreCliente=nombreClienteControl.value;
            const IDCiudad=IDCiudadControl.value;
            const telefonoCliente=telefonoClienteControl.value;
            const ImagenCliente=ImagenClienteControl.value;

            if (!nombreCliente||!IDCiudad||!telefonoCliente||!ImagenCliente){
              this.formularioMultiuso.value.IDCiudad = this.ciudades.findIndex((ciudad:any) => ciudad.nombreCiudad === this.formularioMultiuso.value.IDCiudad) !== -1
              ? this.ciudades.find((ciudad:any) => ciudad.nombreCiudad === this.formularioMultiuso.value.IDCiudad).IDCiudad
              : this.formularioMultiuso.value.IDCiudad;

              alert('Complete todos los campos');
            }else{
              this.crudService.AgregarCliente(this.formularioMultiuso.value).subscribe();
              alert('Cliente agregado');
              this.ruteador.navigateByUrl('/Clientes')
            }

          }

      break; 
      case "proveedor":
        const nombreProveedorControl = this.formularioMultiuso.get('nombreProveedor');
        const IDCiudadPControl = this.formularioMultiuso.get('IDCiudad');
        const telefonoProveedorControl = this.formularioMultiuso.get('telefonoProveedor');
        
        if(nombreProveedorControl&&IDCiudadPControl
          &&telefonoProveedorControl){
            const nombreProveedor=nombreProveedorControl.value;
            const IDCiudad=IDCiudadPControl.value;
            const telefonoProveedor=telefonoProveedorControl.value;

            if (!nombreProveedor||!IDCiudad||!telefonoProveedor){
              this.formularioMultiuso.value.IDCiudad = this.ciudades.findIndex((ciudad:any) => ciudad.nombreCiudad === this.formularioMultiuso.value.IDCiudad) !== -1
              ? this.ciudades.find((ciudad:any) => ciudad.nombreCiudad === this.formularioMultiuso.value.IDCiudad).IDCiudad
              : this.formularioMultiuso.value.IDCiudad;

              alert('Complete todos los campos');
            }else{
              this.crudService.AgregarProveedor(this.formularioMultiuso.value).subscribe();
              alert('Cliente agregado');
              this.ruteador.navigateByUrl('/Index')
            }

          }

      break; 

      case "ciudad": 
      const nombreCiudadControl = this.formularioMultiuso.get('nombreCiudad');
      const IDProvinciaControl = this.formularioMultiuso.get('IDProvincia');
      if(nombreCiudadControl&&IDProvinciaControl){
          alert(nombreCiudadControl?.value+' '+IDProvinciaControl?.value);
        const NombreCiudad=nombreCiudadControl?.value;
        const IDProvincia=IDProvinciaControl?.value;
        if(!NombreCiudad||!IDProvincia){
          alert('Complete todos los campos')
        }else{
          this.crudService.AgregarCiudad(this.formularioMultiuso.value).subscribe();
          alert('Ciudad agregada');
            this.ruteador.navigateByUrl('/Index')
        }
      }
      
      break; 
          }
   

  }
  bloquearComponente: boolean = false;

  onValueChange(event: any): void {
    if (event && event.target && 'value' in event.target && event.target.value) {
      this.descuento = parseFloat(event.target.value);
      if (!isNaN(this.descuento) && this.descuento > 100) {
        this.formularioMultiuso.get('DescuentoInsumo');
      }
    }
  }

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
          this.formularioMultiuso.patchValue({imagen: base64Image});
          this.selectedFile = base64Image; // Actualizar la vista previa
          this.bloquearComponente = false; // Desbloquear el componente cuando termine
        };
        reader.readAsDataURL(compressedBlob);
      });
    }
  }

  ngOnInit(): void {
    console.log("El componente se cargó correctamente");
  }

  Profit(idInsumo: number, idProducto: number): number {

          this.cantidadInsumooControl = this.formularioMultiuso.get('CantidadInsumo');
          this.cantidadProductoControl = this.formularioMultiuso.get('CantidadProducto');
          
    const descuentoPorcentajeProducto= parseInt(this.formularioMultiuso.get('DescuentoProducto')?.value);
    const cantidadProducto=parseInt(this.cantidadProductoControl.value);
    const cantidadInsumo=parseInt(this.cantidadInsumooControl.value);  
    
    if(descuentoPorcentajeProducto>0){
      const descuentoProducto = (descuentoPorcentajeProducto / 100) * this.productos.find((producto:any) => producto.IDProducto === idProducto)?.PrecioProducto;
      this.PrecioProductoCD=this.productos.find((producto:any) => producto.IDProducto === idProducto)?.PrecioProducto - descuentoProducto;
    }else{
        this.PrecioProductoCD=this.productos.find((producto:any) => producto.IDProducto === idProducto)?.PrecioProducto;
    } 
    const descuentoPorcentajeInsumo= parseInt(this.formularioMultiuso.get('DescuentoInsumo')?.value);
    if(descuentoPorcentajeInsumo>0){
        const descuentoInsumo = (descuentoPorcentajeInsumo / 100) * this.insumos.find((insumo:any) => insumo.IDInsumo === idInsumo)?.precio;
        this.PrecioInsumoCD=this.insumos.find((insumo:any) => insumo.IDInsumo === idInsumo)?.precio - descuentoInsumo;
    }else{
        this.PrecioInsumoCD=this.insumos.find((insumo:any) => insumo.IDInsumo === idInsumo)?.precio
    }
    
    const precioFinalProducto= this.PrecioProductoCD*cantidadProducto;
    alert('precioFinalProducto= '+precioFinalProducto)

    const precioFinalInsumo= this.PrecioInsumoCD*cantidadInsumo;
    alert('precioFinalInsumo= '+precioFinalInsumo)
    const profit= precioFinalInsumo-precioFinalProducto;
    return profit;
}

}
