import { CurrencyPipe, DatePipe } from '@angular/common';
import { Component, inject, signal } from '@angular/core';
import { MatButtonModule } from '@angular/material/button';
import { MAT_DIALOG_DATA, MatDialogModule } from '@angular/material/dialog';
import { MatIconModule } from '@angular/material/icon';
import { MatProgressBarModule } from '@angular/material/progress-bar';
import { MatTableModule } from '@angular/material/table';
import { ApiService } from '../../core/api.service';
import { Ticket } from '../../core/models';
import { NotificacionService } from '../../core/notificacion.service';
import { descargarTicketPdf } from './ticket-pdf';

/** Detalle de un ticket con opción de descargar el PDF. */
@Component({
  selector: 'app-ticket-dialog',
  imports: [MatDialogModule, MatButtonModule, MatIconModule, MatTableModule, MatProgressBarModule, CurrencyPipe, DatePipe],
  template: `
    <h2 mat-dialog-title>Ticket N° {{ id }}</h2>
    <mat-dialog-content>
      @if (ticket(); as t) {
        <p class="cabecera">
          <span><strong>{{ t.cliente }}</strong></span>
          <span class="text-muted">{{ t.fecha | date: 'dd/MM/yyyy HH:mm' }}</span>
        </p>
        <table mat-table [dataSource]="t.items ?? []">
          <ng-container matColumnDef="producto">
            <th mat-header-cell *matHeaderCellDef>Producto</th>
            <td mat-cell *matCellDef="let i">{{ i.producto }}</td>
            <td mat-footer-cell *matFooterCellDef><strong>Total</strong></td>
          </ng-container>
          <ng-container matColumnDef="cantidad">
            <th mat-header-cell *matHeaderCellDef class="num">Cant.</th>
            <td mat-cell *matCellDef="let i" class="num">{{ i.cantidad }}</td>
            <td mat-footer-cell *matFooterCellDef class="num">{{ t.cantidadProductos }}</td>
          </ng-container>
          <ng-container matColumnDef="precio">
            <th mat-header-cell *matHeaderCellDef class="num">P. unit.</th>
            <td mat-cell *matCellDef="let i" class="num">{{ i.precioUnitario | currency: 'ARS' : 'symbol-narrow' : '1.2-2' : 'es-AR' }}</td>
            <td mat-footer-cell *matFooterCellDef></td>
          </ng-container>
          <ng-container matColumnDef="total">
            <th mat-header-cell *matHeaderCellDef class="num">Subtotal</th>
            <td mat-cell *matCellDef="let i" class="num">{{ i.total | currency: 'ARS' : 'symbol-narrow' : '1.2-2' : 'es-AR' }}</td>
            <td mat-footer-cell *matFooterCellDef class="num"><strong>{{ t.total | currency: 'ARS' : 'symbol-narrow' : '1.2-2' : 'es-AR' }}</strong></td>
          </ng-container>
          <tr mat-header-row *matHeaderRowDef="columnas"></tr>
          <tr mat-row *matRowDef="let r; columns: columnas"></tr>
          <tr mat-footer-row *matFooterRowDef="columnas"></tr>
        </table>
      } @else {
        <mat-progress-bar mode="indeterminate" />
      }
    </mat-dialog-content>
    <mat-dialog-actions align="end">
      <button mat-button mat-dialog-close>Cerrar</button>
      <button mat-flat-button [disabled]="!ticket() || generando()" (click)="pdf()">
        <mat-icon>picture_as_pdf</mat-icon>Descargar PDF
      </button>
    </mat-dialog-actions>
  `,
  styles: `
    .cabecera { display: flex; justify-content: space-between; gap: 16px; flex-wrap: wrap; }
    table { width: 100%; min-width: 420px; }
    mat-dialog-content { overflow-x: auto; }
    .num { text-align: right; }
  `,
})
export class TicketDialog {
  private readonly api = inject(ApiService);
  private readonly notificacion = inject(NotificacionService);
  readonly id = inject<number>(MAT_DIALOG_DATA);
  readonly ticket = signal<Ticket | null>(null);
  readonly generando = signal(false);
  readonly columnas = ['producto', 'cantidad', 'precio', 'total'];

  constructor() {
    this.api.ticket(this.id).subscribe({
      next: (t) => this.ticket.set(t),
      error: (err) => this.notificacion.error(err),
    });
  }

  async pdf(): Promise<void> {
    const t = this.ticket();
    if (!t) return;
    this.generando.set(true);
    try {
      await descargarTicketPdf(t);
    } catch {
      this.notificacion.error(null, 'No se pudo generar el PDF.');
    } finally {
      this.generando.set(false);
    }
  }
}
