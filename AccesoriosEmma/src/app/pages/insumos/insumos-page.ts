import { Component, computed, signal } from '@angular/core';
import { MatButtonModule } from '@angular/material/button';
import { MatCheckboxModule } from '@angular/material/checkbox';
import { MatIconModule } from '@angular/material/icon';
import { Columna, DataTable } from '../../shared/data-table';
import { CrudPage } from '../../shared/crud-page';
import { CampoFormulario } from '../../shared/form-dialog';
import { PageHeader } from '../../shared/page-header';
import { Insumo } from '../../core/models';

@Component({
  selector: 'app-insumos-page',
  imports: [PageHeader, DataTable, MatButtonModule, MatIconModule, MatCheckboxModule],
  templateUrl: './insumos-page.html',
})
export class InsumosPage extends CrudPage<Insumo> {
  readonly recurso = 'insumos';
  readonly entidad = 'insumo';
  readonly soloBajoMinimo = signal(false);
  readonly cantidadBajoMinimo = computed(() => this.datos().filter((x) => x.bajoMinimo).length);
  readonly visibles = computed(() => (this.soloBajoMinimo() ? this.datos().filter((x) => x.bajoMinimo) : this.datos()));

  readonly columnas: Columna<Insumo>[] = [
    { clave: 'nombre', titulo: 'Nombre' },
    { clave: 'stock', titulo: 'Stock', tipo: 'numero', alertaSi: (x) => x.bajoMinimo },
    { clave: 'stockMinimo', titulo: 'Mínimo', tipo: 'numero' },
    { clave: 'costoPromedio', titulo: 'Costo prom.', tipo: 'moneda' },
    { clave: 'precio', titulo: 'Precio lista', tipo: 'moneda' },
    { clave: 'descuentoCanje', titulo: 'Desc. canje', tipo: 'porcentaje' },
  ];

  protected campos(): CampoFormulario[] {
    return [
      { clave: 'nombre', etiqueta: 'Nombre', tipo: 'texto', requerido: true, maxLength: 50, ancho: true },
      {
        clave: 'precio', etiqueta: 'Precio de lista', tipo: 'numero', requerido: true, min: 0, prefijo: '$',
        ayuda: 'Valor para los canjes.',
      },
      {
        clave: 'costoPromedio', etiqueta: 'Costo promedio', tipo: 'numero', min: 0, prefijo: '$',
        ayuda: 'Se calcula solo con compras y canjes. Corregilo solo si está mal.',
      },
      { clave: 'stock', etiqueta: 'Stock', tipo: 'entero', requerido: true, min: 0 },
      {
        clave: 'stockMinimo', etiqueta: 'Stock mínimo', tipo: 'entero', requerido: true, min: 0,
        ayuda: 'Con esta cantidad o menos aparece en "Insumos para comprar".',
      },
      {
        clave: 'descuentoCanje', etiqueta: 'Descuento pactado en canjes', tipo: 'entero', min: 0, max: 100,
        sufijo: '%', ayuda: 'Opcional', ancho: true,
      },
    ];
  }

  protected override valoresNuevo() {
    return { stockMinimo: 5, costoPromedio: 0 };
  }

  protected valoresDe(i: Insumo) {
    return {
      nombre: i.nombre, precio: i.precio, costoPromedio: i.costoPromedio, stock: i.stock,
      stockMinimo: i.stockMinimo, descuentoCanje: i.descuentoCanje,
    };
  }
}
