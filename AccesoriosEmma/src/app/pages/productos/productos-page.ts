import { Component } from '@angular/core';
import { MatButtonModule } from '@angular/material/button';
import { MatIconModule } from '@angular/material/icon';
import { Columna, DataTable } from '../../shared/data-table';
import { CrudPage } from '../../shared/crud-page';
import { CampoFormulario } from '../../shared/form-dialog';
import { PageHeader } from '../../shared/page-header';
import { Producto } from '../../core/models';

@Component({
  selector: 'app-productos-page',
  imports: [PageHeader, DataTable, MatButtonModule, MatIconModule],
  templateUrl: './productos-page.html',
})
export class ProductosPage extends CrudPage<Producto> {
  readonly recurso = 'productos';
  readonly entidad = 'producto';
  readonly columnas: Columna<Producto>[] = [
    { clave: 'nombre', titulo: 'Nombre' },
    { clave: 'stock', titulo: 'Stock', tipo: 'numero', alertaSi: 5 },
    { clave: 'coste', titulo: 'Coste', tipo: 'moneda' },
    { clave: 'precio', titulo: 'Precio', tipo: 'moneda' },
    { clave: 'ganancia', titulo: 'Ganancia', tipo: 'moneda' },
  ];

  protected campos(): CampoFormulario[] {
    return [
      { clave: 'nombre', etiqueta: 'Nombre', tipo: 'texto', requerido: true, maxLength: 50, ancho: true },
      { clave: 'coste', etiqueta: 'Coste de producción', tipo: 'numero', requerido: true, min: 0, prefijo: '$' },
      { clave: 'precio', etiqueta: 'Precio de venta', tipo: 'numero', requerido: true, min: 0, prefijo: '$' },
      { clave: 'stock', etiqueta: 'Stock', tipo: 'entero', requerido: true, min: 0, sufijo: 'u.' },
    ];
  }

  protected valoresDe(p: Producto) {
    return { nombre: p.nombre, coste: p.coste, precio: p.precio, stock: p.stock };
  }
}
