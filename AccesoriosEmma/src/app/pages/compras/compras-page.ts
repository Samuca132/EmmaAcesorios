import { Component, OnInit, inject, signal } from '@angular/core';
import { MatButtonModule } from '@angular/material/button';
import { MatDialog } from '@angular/material/dialog';
import { MatIconModule } from '@angular/material/icon';
import { ApiService } from '../../core/api.service';
import { Compra } from '../../core/models';
import { NotificacionService } from '../../core/notificacion.service';
import { pedirAnulacion } from '../../shared/anular';
import { AccionFila, Columna, DataTable } from '../../shared/data-table';
import { PageHeader } from '../../shared/page-header';
import { CompraDialog } from './compra-dialog';

@Component({
  selector: 'app-compras-page',
  imports: [PageHeader, DataTable, MatButtonModule, MatIconModule],
  template: `
    <div class="page">
      <app-page-header titulo="Compras" subtitulo="Compras de insumos a proveedores. Al registrar una compra se suma el stock del insumo.">
        <button mat-flat-button (click)="nueva()"><mat-icon>add</mat-icon>Nueva compra</button>
      </app-page-header>
      <app-data-table [columnas]="columnas" [datos]="compras()" [cargando]="cargando()"
                      [conEditar]="false" [conBorrar]="false" textoVacio="No hay compras registradas."
                      [acciones]="acciones" [atenuada]="anulada" (accion)="anular($event.fila)" />
    </div>
  `,
})
export class ComprasPage implements OnInit {
  private readonly api = inject(ApiService);
  private readonly dialog = inject(MatDialog);
  private readonly notificacion = inject(NotificacionService);

  readonly compras = signal<(Compra & { estado: string })[]>([]);
  readonly cargando = signal(true);
  readonly acciones: AccionFila<Compra>[] = [
    { id: 'anular', texto: 'Anular compra', icono: 'block', visible: (x) => x.puedeAnular },
  ];
  readonly anulada = (x: Compra) => x.anulado;

  readonly columnas: Columna<Compra & { estado: string }>[] = [
    { clave: 'fecha', titulo: 'Fecha', tipo: 'fecha' },
    { clave: 'proveedor', titulo: 'Proveedor' },
    { clave: 'insumo', titulo: 'Insumo' },
    { clave: 'cantidad', titulo: 'Cantidad', tipo: 'numero' },
    { clave: 'costo', titulo: 'Costo', tipo: 'moneda' },
    { clave: 'usuario', titulo: 'Registró' },
    { clave: 'estado', titulo: 'Estado' },
  ];

  ngOnInit(): void {
    this.cargar();
  }

  cargar(): void {
    this.cargando.set(true);
    this.api.compras().subscribe({
      next: (c) => {
        this.compras.set(c.map((x) => ({ ...x, estado: x.anulado ? `Anulada: ${x.motivoAnulacion}` : '' })));
        this.cargando.set(false);
      },
      error: (err) => {
        this.cargando.set(false);
        this.notificacion.error(err);
      },
    });
  }

  nueva(): void {
    this.dialog
      .open<CompraDialog, void, Compra[]>(CompraDialog, { width: '720px' })
      .afterClosed()
      .subscribe((compras) => {
        if (compras?.length) {
          this.notificacion.ok(compras.length === 1 ? 'Compra registrada.' : `Se registraron ${compras.length} compras.`);
          this.cargar();
        }
      });
  }

  anular(c: Compra): void {
    pedirAnulacion(this.dialog, {
      titulo: `Anular compra de ${c.cantidad} × ${c.insumo}`,
      mensaje: `La compra a ${c.proveedor} queda en el historial marcada como anulada y se restan ${c.cantidad} unidades del stock de ${c.insumo}. Si ese stock ya se usó, no se puede anular.`,
      anular: (motivo) => this.api.anular<Compra>('compras', c.id, motivo),
    }).subscribe((anulado) => {
      if (anulado) {
        this.notificacion.ok(`Se anuló la compra y se restó el stock de ${c.insumo}.`);
        this.cargar();
      }
    });
  }
}
