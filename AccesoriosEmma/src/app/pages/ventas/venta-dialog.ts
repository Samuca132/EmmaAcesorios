import { CurrencyPipe } from '@angular/common';
import { Component, computed, inject, signal } from '@angular/core';
import { toSignal } from '@angular/core/rxjs-interop';
import { FormArray, FormControl, FormGroup, ReactiveFormsModule, Validators } from '@angular/forms';
import { MatButtonModule } from '@angular/material/button';
import { MAT_DIALOG_DATA, MatDialogModule, MatDialogRef } from '@angular/material/dialog';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatIconModule } from '@angular/material/icon';
import { MatInputModule } from '@angular/material/input';
import { MatProgressSpinnerModule } from '@angular/material/progress-spinner';
import { MatSelectModule } from '@angular/material/select';
import { MatTooltipModule } from '@angular/material/tooltip';
import { ApiService } from '../../core/api.service';
import { Cliente, Producto, Ticket } from '../../core/models';
import { mensajeDeError } from '../../core/notificacion.service';

export interface VentaDialogData {
  /** Si se abre desde el perfil de un cliente, viene preseleccionado. */
  cliente?: Cliente;
}

type Renglon = FormGroup<{ productoId: FormControl<number | null>; cantidad: FormControl<number | null> }>;

/** Modal "Nueva venta": un cliente y uno o más productos. */
@Component({
  selector: 'app-venta-dialog',
  imports: [
    ReactiveFormsModule, MatDialogModule, MatFormFieldModule, MatInputModule, MatSelectModule,
    MatButtonModule, MatIconModule, MatTooltipModule, MatProgressSpinnerModule, CurrencyPipe,
  ],
  template: `
    <h2 mat-dialog-title>Nueva venta</h2>
    <form [formGroup]="form" (ngSubmit)="enviar()">
      <mat-dialog-content>
        @if (data.cliente) {
          <p>Cliente: <strong>{{ data.cliente.nombre }}</strong></p>
        } @else {
          <mat-form-field appearance="outline" class="full-width">
            <mat-label>Cliente</mat-label>
            <mat-select formControlName="clienteId">
              @for (c of clientes(); track c.id) {
                <mat-option [value]="c.id">{{ c.nombre }}@if (c.ciudad) { — {{ c.ciudad }} }</mat-option>
              }
            </mat-select>
            <mat-error>Elegí un cliente.</mat-error>
          </mat-form-field>
        }

        <div formArrayName="items">
          @for (renglon of items.controls; track renglon; let i = $index) {
            <div class="renglon" [formGroupName]="i">
              <mat-form-field appearance="outline" class="producto">
                <mat-label>Producto</mat-label>
                <mat-select formControlName="productoId">
                  @for (p of productos(); track p.id) {
                    <mat-option [value]="p.id" [disabled]="p.stock === 0">
                      {{ p.nombre }} · {{ p.precio | currency: 'ARS' : 'symbol-narrow' : '1.2-2' : 'es-AR' }} · stock {{ p.stock }}
                    </mat-option>
                  }
                </mat-select>
                <mat-error>Elegí un producto.</mat-error>
              </mat-form-field>
              <mat-form-field appearance="outline" class="cantidad">
                <mat-label>Cantidad</mat-label>
                <input matInput type="number" min="1" step="1" inputmode="numeric" formControlName="cantidad" />
                @if (stockDe(renglon) !== null) {
                  <mat-hint>Stock: {{ stockDe(renglon) }}</mat-hint>
                }
                <mat-error>Mínimo 1.</mat-error>
              </mat-form-field>
              <button mat-icon-button type="button" matTooltip="Quitar" [disabled]="items.length === 1"
                      (click)="items.removeAt(i)">
                <mat-icon>remove_circle_outline</mat-icon>
              </button>
            </div>
          }
        </div>

        <button mat-button type="button" (click)="agregar()"><mat-icon>add</mat-icon>Agregar producto</button>

        <p class="total">Total: <strong>{{ total() | currency: 'ARS' : 'symbol-narrow' : '1.2-2' : 'es-AR' }}</strong></p>
        @if (error()) {
          <p class="error" role="alert">{{ error() }}</p>
        }
      </mat-dialog-content>
      <mat-dialog-actions align="end">
        <button mat-button type="button" mat-dialog-close [disabled]="guardando()">Cancelar</button>
        <button mat-flat-button type="submit" [disabled]="guardando()">
          @if (guardando()) { <mat-spinner diameter="18" /> } @else { Concretar venta }
        </button>
      </mat-dialog-actions>
    </form>
  `,
  styles: `
    .renglon { display: flex; gap: 8px; align-items: flex-start; margin-bottom: 8px; }
    .producto { flex: 1 1 auto; min-width: 0; }
    .cantidad { width: 130px; flex: 0 0 auto; }
    .total { text-align: right; font: var(--mat-sys-title-medium); }
    .error { color: var(--mat-sys-error); }
    mat-spinner { display: inline-block; }
  `,
})
export class VentaDialog {
  private readonly api = inject(ApiService);
  private readonly ref = inject(MatDialogRef<VentaDialog, Ticket>);
  readonly data = inject<VentaDialogData>(MAT_DIALOG_DATA);

  readonly clientes = toSignal(this.api.clientes(), { initialValue: [] as Cliente[] });
  readonly productos = toSignal(this.api.productos(), { initialValue: [] as Producto[] });
  readonly guardando = signal(false);
  readonly error = signal('');

  readonly form = new FormGroup({
    clienteId: new FormControl<number | null>(this.data.cliente?.id ?? null, Validators.required),
    items: new FormArray<Renglon>([this.nuevoRenglon()]),
  });

  private readonly valores = toSignal(this.form.valueChanges, { initialValue: this.form.value });

  readonly total = computed(() => {
    const productos = this.productos();
    return (this.valores().items ?? []).reduce((suma, it) => {
      const p = productos.find((x) => x.id === it?.productoId);
      return suma + (p ? p.precio * (Number(it?.cantidad) || 0) : 0);
    }, 0);
  });

  get items() {
    return this.form.controls.items;
  }

  agregar(): void {
    this.items.push(this.nuevoRenglon());
  }

  stockDe(renglon: Renglon): number | null {
    const id = renglon.controls.productoId.value;
    return this.productos().find((p) => p.id === id)?.stock ?? null;
  }

  enviar(): void {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }
    this.guardando.set(true);
    this.error.set('');
    const v = this.form.getRawValue();
    this.api
      .registrarVenta({
        clienteId: v.clienteId!,
        items: v.items.map((i) => ({ productoId: i.productoId!, cantidad: Number(i.cantidad) })),
      })
      .subscribe({
        next: (ticket) => this.ref.close(ticket),
        error: (err) => {
          this.guardando.set(false);
          this.error.set(mensajeDeError(err));
        },
      });
  }

  private nuevoRenglon(): Renglon {
    return new FormGroup({
      productoId: new FormControl<number | null>(null, Validators.required),
      cantidad: new FormControl<number | null>(1, [Validators.required, Validators.min(1)]),
    });
  }
}
