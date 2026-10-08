import { CurrencyPipe, DatePipe } from '@angular/common';
import { Component, inject, signal } from '@angular/core';
import { MatButtonModule } from '@angular/material/button';
import { MAT_DIALOG_DATA, MatDialog, MatDialogModule } from '@angular/material/dialog';
import { MatIconModule } from '@angular/material/icon';
import { MatProgressBarModule } from '@angular/material/progress-bar';
import { MatTableModule } from '@angular/material/table';
import { ApiService } from '../../core/api.service';
import { Ticket } from '../../core/models';
import { NotificacionService } from '../../core/notificacion.service';
import { linkWhatsApp, puedeCompartirArchivo, telefonoWhatsApp } from '../../shared/whatsapp';
import { descargarTicketPdf, ticketPdfComoArchivo } from './ticket-pdf';
import { anularVenta } from './anular-venta';

/**
 * Detalle de un ticket con opción de descargar el PDF y de anularlo.
 * Quien lo abre mira `anulado` al cerrarse para actualizar su lista.
 */
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
        <p class="text-muted registro">Registrada por: {{ t.usuario ?? 'sin dato' }}</p>
        @if (t.anulado) {
          <p class="anulada" role="status">
            <mat-icon>block</mat-icon>
            <span>
              <strong>Venta anulada</strong> el {{ t.anuladoAt | date: 'dd/MM/yyyy HH:mm' }} por {{ t.anuladoPor }}.<br />
              Motivo: {{ t.motivoAnulacion }}
            </span>
          </p>
        }
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
      @if (ticket()?.puedeAnular) {
        <button mat-button class="boton-anular" (click)="anular()"><mat-icon>block</mat-icon>Anular</button>
      }
      <button mat-button mat-dialog-close>Cerrar</button>
      <button mat-stroked-button [disabled]="!ticket() || generando() || ticket()?.anulado" (click)="whatsapp()"
              [title]="ticket()?.clienteTelefono ? 'Enviar a ' + ticket()?.clienteTelefono : 'El cliente no tiene teléfono: vas a elegir el contacto'">
        <mat-icon>send</mat-icon>WhatsApp
      </button>
      <button mat-flat-button [disabled]="!ticket() || generando()" (click)="pdf()">
        <mat-icon>picture_as_pdf</mat-icon>Descargar PDF
      </button>
    </mat-dialog-actions>
  `,
  styles: `
    .cabecera { display: flex; justify-content: space-between; gap: 16px; flex-wrap: wrap; margin-bottom: 0; }
    .registro { margin-top: 4px; font: var(--mat-sys-body-small); }
    table { width: 100%; min-width: 420px; }
    mat-dialog-content { overflow-x: auto; }
    .num { text-align: right; }
    .anulada { display: flex; gap: 8px; align-items: flex-start; padding: 12px; border-radius: 12px;
      background: var(--mat-sys-error-container); color: var(--mat-sys-on-error-container); }
    .boton-anular { color: var(--mat-sys-error); margin-right: auto; }
  `,
})
export class TicketDialog {
  private readonly api = inject(ApiService);
  private readonly notificacion = inject(NotificacionService);
  private readonly dialog = inject(MatDialog);
  readonly id = inject<number>(MAT_DIALOG_DATA);
  anulado = false;
  readonly ticket = signal<Ticket | null>(null);
  readonly generando = signal(false);
  /** PDF listo para compartir (ver whatsapp()). */
  private readonly archivo = signal<File | null>(null);
  readonly columnas = ['producto', 'cantidad', 'precio', 'total'];

  constructor() {
    this.api.ticket(this.id).subscribe({
      next: (t) => this.mostrar(t),
      error: (err) => this.notificacion.error(err),
    });
  }

  anular(): void {
    const t = this.ticket();
    if (!t) return;
    anularVenta(this.dialog, this.api, t).subscribe((actualizado) => {
      if (actualizado) {
        this.anulado = true;
        this.mostrar(actualizado);
        this.notificacion.ok(`Se anuló la venta N° ${t.id} y el stock volvió a los productos.`);
      }
    });
  }

  /**
   * Celular: menú "Compartir" con el PDF adjunto. Computadora: WhatsApp Web
   * con el resumen escrito + descarga del PDF para adjuntarlo.
   *
   * Sin await antes de share()/open(): el navegador solo los permite como
   * respuesta inmediata al clic. Por eso el PDF se prepara al abrir el ticket.
   */
  whatsapp(): void {
    const t = this.ticket();
    if (!t) return;
    const mensaje = resumenParaWhatsApp(t);
    const archivo = this.archivo();
    if (archivo && puedeCompartirArchivo(archivo)) {
      navigator.share({ files: [archivo], title: `Ticket N° ${t.id}`, text: mensaje }).catch((e: DOMException) => {
        if (e.name !== 'AbortError') this.notificacion.error(null, 'No se pudo compartir el comprobante.');
      });
      return;
    }
    window.open(linkWhatsApp(t.clienteTelefono, mensaje), '_blank', 'noopener');
    void this.pdf();
    this.notificacion.ok(
      telefonoWhatsApp(t.clienteTelefono)
        ? 'Se abrió WhatsApp con el mensaje. Adjuntá el PDF que se descargó.'
        : 'El cliente no tiene un teléfono válido: elegí el contacto en WhatsApp y adjuntá el PDF que se descargó.',
    );
  }

  private mostrar(t: Ticket): void {
    this.ticket.set(t);
    this.archivo.set(null);
    ticketPdfComoArchivo(t).then((f) => this.archivo.set(f), () => undefined);
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

const moneda = new Intl.NumberFormat('es-AR', { style: 'currency', currency: 'ARS' });

/** Texto del mensaje: saludo, detalle y total. */
export function resumenParaWhatsApp(t: Ticket): string {
  const nombre = t.cliente.split(' ')[0];
  const renglones = (t.items ?? []).map((i) => `• ${i.cantidad} × ${i.producto}: ${moneda.format(i.total)}`);
  return [
    `¡Hola ${nombre}! Te paso el comprobante de tu compra en Emma Accesorios.`,
    '',
    `Ticket N° ${t.id} · ${new Date(t.fecha.replace(' ', 'T')).toLocaleDateString('es-AR')}`,
    ...renglones,
    `Total: ${moneda.format(t.total)}`,
    '',
    '¡Gracias por tu compra!',
  ].join('\n');
}
