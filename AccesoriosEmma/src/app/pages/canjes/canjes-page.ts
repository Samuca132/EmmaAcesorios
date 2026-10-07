import { Component, OnInit, inject, signal } from '@angular/core';
import { MatButtonModule } from '@angular/material/button';
import { MatDialog } from '@angular/material/dialog';
import { MatIconModule } from '@angular/material/icon';
import { ApiService } from '../../core/api.service';
import { Canje } from '../../core/models';
import { NotificacionService } from '../../core/notificacion.service';
import { Columna, DataTable } from '../../shared/data-table';
import { PageHeader } from '../../shared/page-header';
import { CanjeDialog } from './canje-dialog';

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
    { clave: 'usuario', titulo: 'Registró' },
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
    this.dialog
      .open<CanjeDialog, void, Canje[]>(CanjeDialog, { width: '720px' })
      .afterClosed()
      .subscribe((canjes) => {
        if (canjes?.length) {
          this.notificacion.ok(canjes.length === 1 ? 'Canje registrado.' : `Se registraron ${canjes.length} canjes.`);
          this.cargar();
        }
      });
  }
}
