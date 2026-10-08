import { Component, OnInit, inject, signal } from '@angular/core';
import { MatButtonModule } from '@angular/material/button';
import { MatDialog } from '@angular/material/dialog';
import { MatIconModule } from '@angular/material/icon';
import { ApiService } from '../../core/api.service';
import { PaseVenta } from '../../core/models';
import { NotificacionService } from '../../core/notificacion.service';
import { AccionFila, Columna, DataTable } from '../../shared/data-table';
import { PageHeader } from '../../shared/page-header';
import { anularPase } from './anular-pase';
import { PaseDetalleDialog } from './pase-detalle-dialog';
import { PaseDialog } from './pase-dialog';

type Fila = PaseVenta & { estado: string };

@Component({
  selector: 'app-pases-venta-page',
  imports: [PageHeader, DataTable, MatButtonModule, MatIconModule],
  template: `
    <div class="page">
      <app-page-header titulo="Pasar a venta"
                       subtitulo="Insumos que pasan a ser productos a la venta: los fabricados en el local y la mercadería para revender.">
        <button mat-flat-button (click)="nuevo()"><mat-icon>move_to_inbox</mat-icon>Pasar a venta</button>
      </app-page-header>
      <app-data-table [columnas]="columnas" [datos]="pases()" [cargando]="cargando()"
                      [conEditar]="false" [conBorrar]="false" [conVer]="true" textoVer="Ver detalle"
                      textoVacio="Todavía no se pasó nada a la venta."
                      [acciones]="acciones" [atenuada]="anulado" (ver)="ver($event)" (accion)="anular($event.fila)" />
    </div>
  `,
})
export class PasesVentaPage implements OnInit {
  private readonly api = inject(ApiService);
  private readonly dialog = inject(MatDialog);
  private readonly notificacion = inject(NotificacionService);

  readonly pases = signal<Fila[]>([]);
  readonly cargando = signal(true);
  readonly columnas: Columna<Fila>[] = [
    { clave: 'id', titulo: 'N°' },
    { clave: 'fecha', titulo: 'Fecha', tipo: 'fechaHora' },
    { clave: 'productos', titulo: 'Productos' },
    { clave: 'unidades', titulo: 'Unidades', tipo: 'numero' },
    { clave: 'costoTotal', titulo: 'Costo', tipo: 'moneda' },
    { clave: 'usuario', titulo: 'Registró' },
    { clave: 'estado', titulo: 'Estado' },
  ];
  readonly acciones: AccionFila<PaseVenta>[] = [
    { id: 'anular', texto: 'Anular', icono: 'block', visible: (p) => p.puedeAnular },
  ];
  readonly anulado = (p: PaseVenta) => p.anulado;

  ngOnInit(): void {
    this.cargar();
  }

  cargar(): void {
    this.cargando.set(true);
    this.api.pasesVenta().subscribe({
      next: (p) => {
        this.pases.set(p.map((x) => ({ ...x, estado: x.anulado ? 'Anulado' : '' })));
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
      .open(PaseDialog, { width: '640px' })
      .afterClosed()
      .subscribe((pase) => {
        if (pase) {
          this.notificacion.ok(`Listo: ${pase.productos} a la venta.`);
          this.cargar();
        }
      });
  }

  ver(p: PaseVenta): void {
    const ref = this.dialog.open(PaseDetalleDialog, { data: p.id, width: '560px' });
    const detalle = ref.componentInstance;
    ref.afterClosed().subscribe(() => detalle.anulado && this.cargar());
  }

  anular(p: PaseVenta): void {
    anularPase(this.dialog, this.api, p).subscribe((anulado) => {
      if (anulado) {
        this.notificacion.ok('Se anuló el pase: los insumos volvieron al stock.');
        this.cargar();
      }
    });
  }
}
