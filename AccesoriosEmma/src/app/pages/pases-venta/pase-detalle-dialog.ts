import { CurrencyPipe, DatePipe } from '@angular/common';
import { Component, inject, signal } from '@angular/core';
import { MatButtonModule } from '@angular/material/button';
import { MAT_DIALOG_DATA, MatDialog, MatDialogModule } from '@angular/material/dialog';
import { MatIconModule } from '@angular/material/icon';
import { MatProgressBarModule } from '@angular/material/progress-bar';
import { ApiService } from '../../core/api.service';
import { PaseVenta } from '../../core/models';
import { NotificacionService } from '../../core/notificacion.service';
import { anularPase } from './anular-pase';

/** Detalle de un pase a venta: productos, insumos consumidos y anulación. */
@Component({
  selector: 'app-pase-detalle-dialog',
  imports: [MatDialogModule, MatButtonModule, MatIconModule, MatProgressBarModule, CurrencyPipe, DatePipe],
  template: `
    <h2 mat-dialog-title>Pase a venta N° {{ id }}</h2>
    <mat-dialog-content>
      @if (pase(); as p) {
        <p class="text-muted">{{ p.fecha | date: 'dd/MM/yyyy HH:mm' }} · {{ p.usuario ?? 'sin dato' }}@if (p.nota) { · {{ p.nota }} }</p>
        @if (p.anulado) {
          <p class="anulada" role="status">
            <mat-icon>block</mat-icon>
            <span><strong>Anulado</strong> el {{ p.anuladoAt | date: 'dd/MM/yyyy HH:mm' }} por {{ p.anuladoPor }}.<br />Motivo: {{ p.motivoAnulacion }}</span>
          </p>
        }
        <h3>Productos</h3>
        <ul>
          @for (i of p.items ?? []; track i.productoId) {
            <li>{{ i.cantidad }} × {{ i.producto }} a {{ i.costoUnitario | currency: 'ARS' : 'symbol-narrow' : '1.2-2' : 'es-AR' }}
              = {{ i.costoTotal | currency: 'ARS' : 'symbol-narrow' : '1.2-2' : 'es-AR' }}</li>
          }
        </ul>
        <h3>Insumos consumidos</h3>
        <ul>
          @for (c of p.consumos ?? []; track c.insumoId) {
            <li>{{ c.cantidad }} × {{ c.insumo }} <span class="text-muted">({{ c.costoUnitario | currency: 'ARS' : 'symbol-narrow' : '1.2-2' : 'es-AR' }} c/u)</span></li>
          }
        </ul>
        <p class="total">Costo total: <strong>{{ p.costoTotal | currency: 'ARS' : 'symbol-narrow' : '1.2-2' : 'es-AR' }}</strong></p>
      } @else {
        <mat-progress-bar mode="indeterminate" />
      }
    </mat-dialog-content>
    <mat-dialog-actions align="end">
      @if (pase()?.puedeAnular) {
        <button mat-button class="boton-anular" (click)="anular()"><mat-icon>block</mat-icon>Anular</button>
      }
      <button mat-button mat-dialog-close>Cerrar</button>
    </mat-dialog-actions>
  `,
  styles: `
    h3 { font: var(--mat-sys-title-small); margin: 16px 0 4px; }
    ul { margin: 0; padding-left: 20px; }
    .total { text-align: right; }
    .anulada { display: flex; gap: 8px; align-items: flex-start; padding: 12px; border-radius: 12px;
      background: var(--mat-sys-error-container); color: var(--mat-sys-on-error-container); }
    .boton-anular { color: var(--mat-sys-error); margin-right: auto; }
  `,
})
export class PaseDetalleDialog {
  private readonly api = inject(ApiService);
  private readonly dialog = inject(MatDialog);
  private readonly notificacion = inject(NotificacionService);
  readonly id = inject<number>(MAT_DIALOG_DATA);
  readonly pase = signal<PaseVenta | null>(null);
  /** Quien lo abre lo mira al cerrarse para actualizar su lista. */
  anulado = false;

  constructor() {
    this.api.paseVenta(this.id).subscribe({
      next: (p) => this.pase.set(p),
      error: (err) => this.notificacion.error(err),
    });
  }

  anular(): void {
    const p = this.pase();
    if (!p) return;
    anularPase(this.dialog, this.api, p).subscribe((actualizado) => {
      if (actualizado) {
        this.anulado = true;
        this.pase.set(actualizado);
        this.notificacion.ok('Se anuló el pase: los insumos volvieron al stock.');
      }
    });
  }
}
