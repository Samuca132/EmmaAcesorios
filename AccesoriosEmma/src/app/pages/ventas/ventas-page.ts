import { Component, OnInit, inject, signal } from '@angular/core';
import { toSignal } from '@angular/core/rxjs-interop';
import { FormControl, FormGroup, ReactiveFormsModule } from '@angular/forms';
import { MatButtonModule } from '@angular/material/button';
import { MatDialog } from '@angular/material/dialog';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatIconModule } from '@angular/material/icon';
import { MatInputModule } from '@angular/material/input';
import { MatSelectModule } from '@angular/material/select';
import { debounceTime } from 'rxjs';
import { ApiService } from '../../core/api.service';
import { Ciudad, Cliente, Producto, Ticket } from '../../core/models';
import { NotificacionService } from '../../core/notificacion.service';
import { Columna, DataTable } from '../../shared/data-table';
import { PageHeader } from '../../shared/page-header';
import { TicketDialog } from './ticket-dialog';
import { VentaDialog, VentaDialogData } from './venta-dialog';

@Component({
  selector: 'app-ventas-page',
  imports: [
    PageHeader, DataTable, ReactiveFormsModule, MatButtonModule, MatIconModule, MatFormFieldModule,
    MatInputModule, MatSelectModule,
  ],
  template: `
    <div class="page">
      <app-page-header titulo="Ventas" subtitulo="Cada fila es un ticket. Tocalo para ver el detalle o bajar el PDF.">
        <button mat-flat-button (click)="nuevaVenta()"><mat-icon>add_shopping_cart</mat-icon>Nueva venta</button>
      </app-page-header>

      <form class="filtros" [formGroup]="filtros">
        <mat-form-field appearance="outline" subscriptSizing="dynamic">
          <mat-label>Cliente</mat-label>
          <mat-select formControlName="clienteId">
            <mat-option [value]="null">Todos</mat-option>
            @for (c of clientes(); track c.id) { <mat-option [value]="c.id">{{ c.nombre }}</mat-option> }
          </mat-select>
        </mat-form-field>
        <mat-form-field appearance="outline" subscriptSizing="dynamic">
          <mat-label>Producto</mat-label>
          <mat-select formControlName="productoId">
            <mat-option [value]="null">Todos</mat-option>
            @for (p of productos(); track p.id) { <mat-option [value]="p.id">{{ p.nombre }}</mat-option> }
          </mat-select>
        </mat-form-field>
        <mat-form-field appearance="outline" subscriptSizing="dynamic">
          <mat-label>Ciudad</mat-label>
          <mat-select formControlName="ciudadId">
            <mat-option [value]="null">Todas</mat-option>
            @for (c of ciudades(); track c.id) { <mat-option [value]="c.id">{{ c.nombre }}</mat-option> }
          </mat-select>
        </mat-form-field>
        <mat-form-field appearance="outline" subscriptSizing="dynamic">
          <mat-label>Desde</mat-label>
          <input matInput type="date" formControlName="desde" />
        </mat-form-field>
        <mat-form-field appearance="outline" subscriptSizing="dynamic">
          <mat-label>Hasta</mat-label>
          <input matInput type="date" formControlName="hasta" />
        </mat-form-field>
        <button mat-button type="button" (click)="filtros.reset()"><mat-icon>filter_alt_off</mat-icon>Limpiar</button>
      </form>

      <app-data-table [columnas]="columnas" [datos]="tickets()" [cargando]="cargando()"
                      [conEditar]="false" [conBorrar]="false" [conVer]="true" iconoVer="receipt_long"
                      textoVer="Ver ticket" textoVacio="No hay ventas registradas." (ver)="verTicket($event)" />
    </div>
  `,
  styles: `
    .filtros { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 12px; align-items: center; margin-bottom: 16px; }
  `,
})
export class VentasPage implements OnInit {
  private readonly api = inject(ApiService);
  private readonly dialog = inject(MatDialog);
  private readonly notificacion = inject(NotificacionService);

  readonly clientes = toSignal(this.api.clientes(), { initialValue: [] as Cliente[] });
  readonly productos = toSignal(this.api.productos(), { initialValue: [] as Producto[] });
  readonly ciudades = toSignal(this.api.ciudades(), { initialValue: [] as Ciudad[] });
  readonly tickets = signal<Ticket[]>([]);
  readonly cargando = signal(true);

  readonly filtros = new FormGroup({
    clienteId: new FormControl<number | null>(null),
    productoId: new FormControl<number | null>(null),
    ciudadId: new FormControl<number | null>(null),
    desde: new FormControl<string | null>(null),
    hasta: new FormControl<string | null>(null),
  });

  readonly columnas: Columna<Ticket>[] = [
    { clave: 'id', titulo: 'N°' },
    { clave: 'fecha', titulo: 'Fecha', tipo: 'fechaHora' },
    { clave: 'cliente', titulo: 'Cliente' },
    { clave: 'ciudad', titulo: 'Ciudad' },
    { clave: 'cantidadProductos', titulo: 'Productos', tipo: 'numero' },
    { clave: 'total', titulo: 'Total', tipo: 'moneda' },
  ];

  ngOnInit(): void {
    this.cargar();
    this.filtros.valueChanges.pipe(debounceTime(250)).subscribe(() => this.cargar());
  }

  cargar(): void {
    this.cargando.set(true);
    this.api.ventas(this.filtros.getRawValue()).subscribe({
      next: (t) => {
        this.tickets.set(t);
        this.cargando.set(false);
      },
      error: (err) => {
        this.cargando.set(false);
        this.notificacion.error(err);
      },
    });
  }

  nuevaVenta(): void {
    this.dialog
      .open<VentaDialog, VentaDialogData, Ticket>(VentaDialog, { data: {}, width: '640px' })
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
}
