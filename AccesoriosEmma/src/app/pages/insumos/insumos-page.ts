import { Component } from '@angular/core';
import { MatButtonModule } from '@angular/material/button';
import { MatIconModule } from '@angular/material/icon';
import { Columna, DataTable } from '../../shared/data-table';
import { CrudPage } from '../../shared/crud-page';
import { CampoFormulario } from '../../shared/form-dialog';
import { PageHeader } from '../../shared/page-header';
import { Insumo } from '../../core/models';

@Component({
  selector: 'app-insumos-page',
  imports: [PageHeader, DataTable, MatButtonModule, MatIconModule],
  templateUrl: './insumos-page.html',
})
export class InsumosPage extends CrudPage<Insumo> {
  readonly recurso = 'insumos';
  readonly entidad = 'insumo';
  readonly columnas: Columna<Insumo>[] = [
    { clave: 'nombre', titulo: 'Nombre' },
    { clave: 'stock', titulo: 'Stock', tipo: 'numero', alertaSi: 5 },
    { clave: 'precio', titulo: 'Precio', tipo: 'moneda' },
    { clave: 'descuentoCanje', titulo: 'Desc. canje', tipo: 'porcentaje' },
  ];

  protected campos(): CampoFormulario[] {
    return [
      { clave: 'nombre', etiqueta: 'Nombre', tipo: 'texto', requerido: true, maxLength: 50, ancho: true },
      { clave: 'precio', etiqueta: 'Precio', tipo: 'numero', requerido: true, min: 0, prefijo: '$' },
      { clave: 'stock', etiqueta: 'Stock', tipo: 'entero', requerido: true, min: 0 },
      {
        clave: 'descuentoCanje', etiqueta: 'Descuento pactado en canjes', tipo: 'entero', min: 0, max: 100,
        sufijo: '%', ayuda: 'Opcional', ancho: true,
      },
    ];
  }

  protected valoresDe(i: Insumo) {
    return { nombre: i.nombre, precio: i.precio, stock: i.stock, descuentoCanje: i.descuentoCanje };
  }
}
