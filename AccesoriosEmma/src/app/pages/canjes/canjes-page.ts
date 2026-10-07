import { Component, OnInit, inject, signal } from '@angular/core';
import { MatButtonModule } from '@angular/material/button';
import { MatDialog } from '@angular/material/dialog';
import { MatIconModule } from '@angular/material/icon';
import { forkJoin } from 'rxjs';
import { ApiService } from '../../core/api.service';
import { Canje } from '../../core/models';
import { NotificacionService } from '../../core/notificacion.service';
import { Columna, DataTable } from '../../shared/data-table';
import { FormDialog, FormDialogData } from '../../shared/form-dialog';
import { PageHeader } from '../../shared/page-header';

@Component({
  selector: 'app-canjes-page',
  imports: [PageHeader, DataTable, MatButtonModule, MatIconModule],
  template: `
    <div class="page">
      <app-page-header titulo="Canjes" subtitulo="Productos entregados a proveedores a cambio de insumos.">
        <button mat-flat-button (click)="nuevo()"><mat-icon>add</mat-icon>Nuevo canje</button>
      </app-page-header>
      <app-data-table [columnas]="columnas" [datos]="canjes()" [cargando]="cargando()"
                      [conEditar]="false" [conBorrar]="false" textoVacio="No hay canjes registrados." />
    </div>
  `,
})
export class CanjesPage implements OnInit {
  private readonly api = inject(ApiService);
  private readonly dialog = inject(MatDialog);
  private readonly notificacion = inject(NotificacionService);

  readonly canjes = signal<Canje[]>([]);
  readonly cargando = signal(true);
  readonly columnas: Columna<Canje>[] = [
    { clave: 'fecha', titulo: 'Fecha', tipo: 'fecha' },
    { clave: 'proveedor', titulo: 'Proveedor' },
    { clave: 'producto', titulo: 'Producto entregado' },
    { clave: 'cantidadProducto', titulo: 'Cant.', tipo: 'numero' },
    { clave: 'insumo', titulo: 'Insumo recibido' },
    { clave: 'cantidadInsumo', titulo: 'Cant.', tipo: 'numero' },
    { clave: 'profit', titulo: 'Ganancia', tipo: 'moneda' },
  ];

  ngOnInit(): void {
    this.cargar();
  }

  cargar(): void {
    this.cargando.set(true);
    this.api.canjes().subscribe({
      next: (c) => {
        this.canjes.set(c);
        this.cargando.set(false);
      },
      error: (err) => {
        this.cargando.set(false);
        this.notificacion.error(err);
      },
    });
  }

  nuevo(): void {
    forkJoin([this.api.proveedores(), this.api.productos(), this.api.insumos()]).subscribe(
      ([proveedores, productos, insumos]) => {
        this.dialog
          .open<FormDialog<Canje>, FormDialogData<Canje>, Canje>(FormDialog, {
            data: {
              titulo: 'Nuevo canje',
              textoGuardar: 'Registrar',
              campos: [
                {
                  clave: 'proveedorId', etiqueta: 'Proveedor', tipo: 'select', requerido: true, ancho: true,
                  opciones: proveedores.map((p) => ({ valor: p.id, texto: p.nombre })),
                },
                {
                  clave: 'productoId', etiqueta: 'Producto que entregás', tipo: 'select', requerido: true,
                  opciones: productos.map((p) => ({ valor: p.id, texto: `${p.nombre} (stock ${p.stock})` })),
                },
                { clave: 'cantidadProducto', etiqueta: 'Cantidad', tipo: 'entero', requerido: true, min: 1 },
                {
                  clave: 'insumoId', etiqueta: 'Insumo que recibís', tipo: 'select', requerido: true,
                  opciones: insumos.map((i) => ({ valor: i.id, texto: i.nombre })),
                },
                { clave: 'cantidadInsumo', etiqueta: 'Cantidad', tipo: 'entero', requerido: true, min: 1 },
                { clave: 'descuentoProducto', etiqueta: 'Descuento producto', tipo: 'numero', min: 0, max: 100, sufijo: '%' },
                { clave: 'descuentoInsumo', etiqueta: 'Descuento insumo', tipo: 'numero', min: 0, max: 100, sufijo: '%' },
              ],
              guardar: (v) => this.api.registrarCanje(v),
            },
          })
          .afterClosed()
          .subscribe((canje) => {
            if (canje) {
              this.notificacion.ok('Canje registrado.');
              this.cargar();
            }
          });
      },
    );
  }
}
