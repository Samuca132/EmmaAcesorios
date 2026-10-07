import { Component, OnInit, inject, signal } from '@angular/core';
import { MatButtonModule } from '@angular/material/button';
import { MatDialog } from '@angular/material/dialog';
import { MatIconModule } from '@angular/material/icon';
import { forkJoin } from 'rxjs';
import { ApiService } from '../../core/api.service';
import { Compra } from '../../core/models';
import { NotificacionService } from '../../core/notificacion.service';
import { Columna, DataTable } from '../../shared/data-table';
import { FormDialog, FormDialogData } from '../../shared/form-dialog';
import { PageHeader } from '../../shared/page-header';

@Component({
  selector: 'app-compras-page',
  imports: [PageHeader, DataTable, MatButtonModule, MatIconModule],
  template: `
    <div class="page">
      <app-page-header titulo="Compras" subtitulo="Compras de insumos a proveedores. Al registrar una compra se suma el stock del insumo.">
        <button mat-flat-button (click)="nueva()"><mat-icon>add</mat-icon>Nueva compra</button>
      </app-page-header>
      <app-data-table [columnas]="columnas" [datos]="compras()" [cargando]="cargando()"
                      [conEditar]="false" [conBorrar]="false" textoVacio="No hay compras registradas." />
    </div>
  `,
})
export class ComprasPage implements OnInit {
  private readonly api = inject(ApiService);
  private readonly dialog = inject(MatDialog);
  private readonly notificacion = inject(NotificacionService);

  readonly compras = signal<Compra[]>([]);
  readonly cargando = signal(true);
  readonly columnas: Columna<Compra>[] = [
    { clave: 'fecha', titulo: 'Fecha', tipo: 'fecha' },
    { clave: 'proveedor', titulo: 'Proveedor' },
    { clave: 'insumo', titulo: 'Insumo' },
    { clave: 'cantidad', titulo: 'Cantidad', tipo: 'numero' },
    { clave: 'costo', titulo: 'Costo', tipo: 'moneda' },
  ];

  ngOnInit(): void {
    this.cargar();
  }

  cargar(): void {
    this.cargando.set(true);
    this.api.compras().subscribe({
      next: (c) => {
        this.compras.set(c);
        this.cargando.set(false);
      },
      error: (err) => {
        this.cargando.set(false);
        this.notificacion.error(err);
      },
    });
  }

  nueva(): void {
    forkJoin([this.api.proveedores(), this.api.insumos()]).subscribe(([proveedores, insumos]) => {
      this.dialog
        .open<FormDialog<Compra>, FormDialogData<Compra>, Compra>(FormDialog, {
          data: {
            titulo: 'Nueva compra',
            textoGuardar: 'Registrar',
            campos: [
              {
                clave: 'proveedorId', etiqueta: 'Proveedor', tipo: 'select', requerido: true,
                opciones: proveedores.map((p) => ({ valor: p.id, texto: p.nombre })),
              },
              {
                clave: 'insumoId', etiqueta: 'Insumo', tipo: 'select', requerido: true,
                opciones: insumos.map((i) => ({ valor: i.id, texto: `${i.nombre} (stock ${i.stock})` })),
              },
              { clave: 'cantidad', etiqueta: 'Cantidad', tipo: 'entero', requerido: true, min: 1 },
              { clave: 'costo', etiqueta: 'Costo total', tipo: 'numero', requerido: true, min: 0, prefijo: '$' },
              { clave: 'fecha', etiqueta: 'Fecha', tipo: 'fecha', ayuda: 'Si la dejás vacía se usa la de hoy' },
            ],
            valores: { fecha: new Date().toISOString().slice(0, 10) },
            guardar: (v) => this.api.registrarCompra({ ...v, fecha: v['fecha'] || null }),
          },
        })
        .afterClosed()
        .subscribe((compra) => {
          if (compra) {
            this.notificacion.ok('Compra registrada.');
            this.cargar();
          }
        });
    });
  }
}
