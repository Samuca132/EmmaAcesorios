import { Component, computed, signal } from '@angular/core';
import { MatButtonModule } from '@angular/material/button';
import { MatCheckboxModule } from '@angular/material/checkbox';
import { MatIconModule } from '@angular/material/icon';
import { AccionFila, Columna, DataTable } from '../../shared/data-table';
import { ComposicionDialog } from './composicion-dialog';
import { CrudPage } from '../../shared/crud-page';
import { CampoFormulario } from '../../shared/form-dialog';
import { PageHeader } from '../../shared/page-header';
import { Producto } from '../../core/models';

type FilaProducto = Producto & { composicion: string };

@Component({
  selector: 'app-productos-page',
  imports: [PageHeader, DataTable, MatButtonModule, MatIconModule, MatCheckboxModule],
  templateUrl: './productos-page.html',
})
export class ProductosPage extends CrudPage<Producto, FilaProducto> {
  readonly recurso = 'productos';
  readonly entidad = 'producto';
  readonly soloBajoMinimo = signal(false);
  readonly cantidadBajoMinimo = computed(() => this.datos().filter((x) => x.bajoMinimo).length);
  readonly visibles = computed<FilaProducto[]>(() =>
    (this.soloBajoMinimo() ? this.datos().filter((x) => x.bajoMinimo) : this.datos()).map((p) => ({
      ...p,
      composicion: p.tieneComposicion ? 'Cargada' : 'Falta',
    })),
  );

  readonly columnas: Columna<FilaProducto>[] = [
    { clave: 'nombre', titulo: 'Nombre' },
    { clave: 'stock', titulo: 'Stock', tipo: 'numero', alertaSi: (x) => x.bajoMinimo },
    { clave: 'stockMinimo', titulo: 'Mínimo', tipo: 'numero' },
    { clave: 'coste', titulo: 'Coste', tipo: 'moneda' },
    { clave: 'precio', titulo: 'Precio', tipo: 'moneda' },
    { clave: 'ganancia', titulo: 'Ganancia', tipo: 'moneda' },
    { clave: 'composicion', titulo: 'Composición', alertaSi: (p) => !p.tieneComposicion },
  ];

  protected override accionesExtra(): AccionFila<Producto>[] {
    return [{ id: 'composicion', texto: 'Composición', icono: 'account_tree' }];
  }

  protected override alAccion(id: string, producto: Producto): void {
    if (id === 'composicion') {
      this.dialog
        .open(ComposicionDialog, { data: producto, width: '640px' })
        .afterClosed()
        .subscribe((c) => {
          if (c) {
            this.notificacion.ok(`Se guardó la composición de "${producto.nombre}".`);
            this.cargar();
          }
        });
    }
  }

  protected campos(): CampoFormulario[] {
    return [
      { clave: 'nombre', etiqueta: 'Nombre', tipo: 'texto', requerido: true, maxLength: 50, ancho: true },
      {
        clave: 'coste', etiqueta: 'Coste por unidad', tipo: 'numero', requerido: true, min: 0, prefijo: '$',
        ayuda: 'Se recalcula solo al pasar a venta.',
      },
      { clave: 'precio', etiqueta: 'Precio de venta', tipo: 'numero', requerido: true, min: 0, prefijo: '$' },
      { clave: 'stock', etiqueta: 'Stock', tipo: 'entero', requerido: true, min: 0, sufijo: 'u.' },
      {
        clave: 'stockMinimo', etiqueta: 'Stock mínimo', tipo: 'entero', requerido: true, min: 0, sufijo: 'u.',
        ayuda: 'Con esta cantidad o menos aparece en "Productos para reponer".',
      },
    ];
  }

  protected override valoresNuevo() {
    return { stockMinimo: 5 };
  }

  protected valoresDe(p: Producto) {
    return { nombre: p.nombre, coste: p.coste, precio: p.precio, stock: p.stock, stockMinimo: p.stockMinimo };
  }
}
