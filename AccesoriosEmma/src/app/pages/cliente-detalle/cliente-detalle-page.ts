import { CurrencyPipe } from '@angular/common';
import { Component, OnInit, inject, input, signal } from '@angular/core';
import { MatButtonModule } from '@angular/material/button';
import { MatCardModule } from '@angular/material/card';
import { MatDialog } from '@angular/material/dialog';
import { MatIconModule } from '@angular/material/icon';
import { MatProgressBarModule } from '@angular/material/progress-bar';
import { Router, RouterLink } from '@angular/router';
import { filter, switchMap } from 'rxjs';
import { ApiService } from '../../core/api.service';
import { Ciudad, Cliente, Ticket } from '../../core/models';
import { NotificacionService } from '../../core/notificacion.service';
import { confirmar } from '../../shared/confirm-dialog';
import { Columna, DataTable } from '../../shared/data-table';
import { FormDialog, FormDialogData } from '../../shared/form-dialog';
import { camposPersona } from '../proveedores/proveedores-page';
import { TicketDialog } from '../ventas/ticket-dialog';
import { VentaDialog, VentaDialogData } from '../ventas/venta-dialog';

/** Perfil de un cliente: datos, historial de tickets y alta de ventas. */
@Component({
  selector: 'app-cliente-detalle-page',
  imports: [DataTable, MatCardModule, MatButtonModule, MatIconModule, MatProgressBarModule, RouterLink, CurrencyPipe],
  template: `
    <div class="page">
      <a mat-button routerLink="/clientes"><mat-icon>arrow_back</mat-icon>Clientes</a>

      @if (cliente(); as c) {
        <mat-card appearance="outlined" class="perfil">
          <mat-card-header>
            <div mat-card-avatar class="avatar">{{ c.nombre.charAt(0).toUpperCase() }}</div>
            <mat-card-title>{{ c.nombre }}</mat-card-title>
            <mat-card-subtitle>
              {{ c.ciudad ?? 'Sin ciudad' }}@if (c.telefono) { · {{ c.telefono }} }
            </mat-card-subtitle>
          </mat-card-header>
          <mat-card-content class="stats">
            <div><span class="valor">{{ c.compras }}</span><span class="text-muted">compras</span></div>
            <div>
              <span class="valor">{{ c.totalComprado | currency: 'ARS' : 'symbol-narrow' : '1.0-0' : 'es-AR' }}</span>
              <span class="text-muted">total comprado</span>
            </div>
          </mat-card-content>
          <mat-card-actions align="end">
            <button mat-button (click)="borrar(c)"><mat-icon>delete</mat-icon>Borrar</button>
            <button mat-stroked-button (click)="editar(c)"><mat-icon>edit</mat-icon>Editar</button>
            <button mat-flat-button (click)="nuevaVenta(c)"><mat-icon>add_shopping_cart</mat-icon>Nueva venta</button>
          </mat-card-actions>
        </mat-card>

        <h2 class="titulo-seccion">Historial de compras</h2>
        <app-data-table [columnas]="columnas" [datos]="tickets()" [cargando]="cargandoTickets()"
                        [conEditar]="false" [conBorrar]="false" [conVer]="true" iconoVer="receipt_long"
                        textoVer="Ver ticket" textoVacio="Este cliente todavía no compró nada." (ver)="verTicket($event)" />
      } @else {
        <mat-progress-bar mode="indeterminate" />
      }
    </div>
  `,
  styles: `
    .perfil { margin: 8px 0 24px; }
    .avatar {
      display: grid; place-items: center; font: var(--mat-sys-title-large);
      background: var(--mat-sys-primary-container); color: var(--mat-sys-on-primary-container);
    }
    .stats { display: flex; gap: 32px; padding-top: 16px; }
    .stats div { display: flex; flex-direction: column; }
    .valor { font: var(--mat-sys-headline-small); }
    .titulo-seccion { font: var(--mat-sys-title-large); margin: 0 0 12px; }
  `,
})
export class ClienteDetallePage implements OnInit {
  /** Parámetro :id de la ruta (withComponentInputBinding). */
  readonly id = input.required<string>();

  private readonly api = inject(ApiService);
  private readonly dialog = inject(MatDialog);
  private readonly router = inject(Router);
  private readonly notificacion = inject(NotificacionService);

  readonly cliente = signal<Cliente | null>(null);
  readonly tickets = signal<Ticket[]>([]);
  readonly cargandoTickets = signal(true);
  private ciudades: Ciudad[] = [];

  readonly columnas: Columna<Ticket>[] = [
    { clave: 'id', titulo: 'N°' },
    { clave: 'fecha', titulo: 'Fecha', tipo: 'fechaHora' },
    { clave: 'cantidadProductos', titulo: 'Productos', tipo: 'numero' },
    { clave: 'total', titulo: 'Total', tipo: 'moneda' },
    { clave: 'usuario', titulo: 'Registró' },
  ];

  ngOnInit(): void {
    this.cargar();
    this.api.ciudades().subscribe((c) => (this.ciudades = c));
  }

  cargar(): void {
    const id = Number(this.id());
    this.api.obtener<Cliente>('clientes', id).subscribe({
      next: (c) => this.cliente.set(c),
      error: (err) => {
        this.notificacion.error(err);
        this.router.navigate(['/clientes']);
      },
    });
    this.cargandoTickets.set(true);
    this.api.ventas({ clienteId: id }).subscribe({
      next: (t) => {
        this.tickets.set(t);
        this.cargandoTickets.set(false);
      },
      error: () => this.cargandoTickets.set(false),
    });
  }

  nuevaVenta(cliente: Cliente): void {
    this.dialog
      .open<VentaDialog, VentaDialogData, Ticket>(VentaDialog, { data: { cliente }, width: '640px' })
      .afterClosed()
      .subscribe((ticket) => {
        if (ticket) {
          this.notificacion.ok(`Venta registrada (ticket N° ${ticket.id}).`);
          this.cargar();
        }
      });
  }

  verTicket(t: Ticket): void {
    this.dialog.open(TicketDialog, { data: t.id, width: '640px' });
  }

  editar(c: Cliente): void {
    this.dialog
      .open<FormDialog<Cliente>, FormDialogData<Cliente>, Cliente>(FormDialog, {
        data: {
          titulo: 'Editar cliente',
          campos: camposPersona(this.ciudades),
          valores: { nombre: c.nombre, ciudadId: c.ciudadId, telefono: c.telefono },
          guardar: (v) => this.api.actualizar<Cliente>('clientes', c.id, v),
        },
      })
      .afterClosed()
      .subscribe((actualizado) => {
        if (actualizado) {
          this.cliente.set(actualizado);
          this.notificacion.ok('Cambios guardados.');
        }
      });
  }

  borrar(c: Cliente): void {
    confirmar(this.dialog, {
      titulo: 'Borrar cliente',
      mensaje: `¿Seguro que querés borrar a "${c.nombre}"? Su historial de ventas se conserva.`,
      confirmar: 'Borrar',
    })
      .pipe(filter(Boolean), switchMap(() => this.api.borrar('clientes', c.id)))
      .subscribe({
        next: () => {
          this.notificacion.ok(`Se borró "${c.nombre}".`);
          this.router.navigate(['/clientes']);
        },
        error: (err) => this.notificacion.error(err),
      });
  }
}
