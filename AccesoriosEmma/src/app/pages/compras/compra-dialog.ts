import { CurrencyPipe } from '@angular/common';
import { Component, computed, inject, signal } from '@angular/core';
import { toSignal } from '@angular/core/rxjs-interop';
import { FormArray, FormControl, FormGroup, ReactiveFormsModule, Validators } from '@angular/forms';
import { MatButtonModule } from '@angular/material/button';
import { MatDialogModule, MatDialogRef } from '@angular/material/dialog';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatIconModule } from '@angular/material/icon';
import { MatInputModule } from '@angular/material/input';
import { MatProgressSpinnerModule } from '@angular/material/progress-spinner';
import { MatSelectModule } from '@angular/material/select';
import { MatTooltipModule } from '@angular/material/tooltip';
import { ApiService } from '../../core/api.service';
import { Compra, Insumo, Proveedor } from '../../core/models';
import { mensajeDeError } from '../../core/notificacion.service';

type Renglon = FormGroup<{
  insumoId: FormControl<number | null>;
  cantidad: FormControl<number | null>;
  costo: FormControl<number | null>;
}>;

/** Modal "Nueva compra": un proveedor y uno o más insumos. */
@Component({
  selector: 'app-compra-dialog',
  imports: [
    ReactiveFormsModule, MatDialogModule, MatFormFieldModule, MatInputModule, MatSelectModule,
    MatButtonModule, MatIconModule, MatTooltipModule, MatProgressSpinnerModule, CurrencyPipe,
  ],
  template: `
    <h2 mat-dialog-title>Nueva compra</h2>
    <form [formGroup]="form" (ngSubmit)="enviar()">
      <mat-dialog-content>
        <div class="cabecera">
          <mat-form-field appearance="outline" class="proveedor">
            <mat-label>Proveedor</mat-label>
            <mat-select formControlName="proveedorId">
              @for (p of proveedores(); track p.id) {
                <mat-option [value]="p.id">{{ p.nombre }}</mat-option>
              }
            </mat-select>
            <mat-error>Elegí un proveedor.</mat-error>
          </mat-form-field>
          <mat-form-field appearance="outline" class="fecha">
            <mat-label>Fecha</mat-label>
            <input matInput type="date" formControlName="fecha" />
          </mat-form-field>
        </div>

        <div formArrayName="items">
          @for (renglon of items.controls; track renglon; let i = $index) {
            <div class="renglon" [formGroupName]="i">
              <mat-form-field appearance="outline" class="insumo">
                <mat-label>Insumo</mat-label>
                <mat-select formControlName="insumoId">
                  @for (ins of insumos(); track ins.id) {
                    <mat-option [value]="ins.id">{{ ins.nombre }} · stock {{ ins.stock }}</mat-option>
                  }
                </mat-select>
                <mat-error>Elegí un insumo.</mat-error>
              </mat-form-field>
              <mat-form-field appearance="outline" class="numero">
                <mat-label>Cantidad</mat-label>
                <input matInput type="number" min="1" step="1" inputmode="numeric" formControlName="cantidad" />
                <mat-error>Mínimo 1.</mat-error>
              </mat-form-field>
              <mat-form-field appearance="outline" class="numero">
                <mat-label>Costo total</mat-label>
                <span matTextPrefix>$&nbsp;</span>
                <input matInput type="number" min="0" step="0.01" inputmode="decimal" formControlName="costo" />
                <mat-error>Ingresá el costo.</mat-error>
              </mat-form-field>
              <button mat-icon-button type="button" matTooltip="Quitar" [disabled]="items.length === 1"
                      (click)="items.removeAt(i)">
                <mat-icon>remove_circle_outline</mat-icon>
              </button>
            </div>
          }
        </div>

        <button mat-button type="button" (click)="agregar()"><mat-icon>add</mat-icon>Agregar insumo</button>

        <p class="total">Total: <strong>{{ total() | currency: 'ARS' : 'symbol-narrow' : '1.2-2' : 'es-AR' }}</strong></p>
        @if (error()) {
          <p class="error" role="alert">{{ error() }}</p>
        }
      </mat-dialog-content>
      <mat-dialog-actions align="end">
        <button mat-button type="button" mat-dialog-close [disabled]="guardando()">Cancelar</button>
        <button mat-flat-button type="submit" [disabled]="guardando()">
          @if (guardando()) { <mat-spinner diameter="18" /> } @else { Registrar compra }
        </button>
      </mat-dialog-actions>
    </form>
  `,
  styles: `
    .cabecera, .renglon { display: flex; gap: 8px; align-items: flex-start; flex-wrap: wrap; }
    .proveedor, .insumo { flex: 1 1 220px; min-width: 0; }
    .fecha { flex: 0 0 170px; }
    .numero { flex: 0 1 140px; min-width: 110px; }
    .total { text-align: right; font: var(--mat-sys-title-medium); }
    .error { color: var(--mat-sys-error); }
    mat-spinner { display: inline-block; }
  `,
})
export class CompraDialog {
  private readonly api = inject(ApiService);
  private readonly ref = inject(MatDialogRef<CompraDialog, Compra[]>);

  readonly proveedores = toSignal(this.api.proveedores(), { initialValue: [] as Proveedor[] });
  readonly insumos = toSignal(this.api.insumos(), { initialValue: [] as Insumo[] });
  readonly guardando = signal(false);
  readonly error = signal('');

  readonly form = new FormGroup({
    proveedorId: new FormControl<number | null>(null, Validators.required),
    fecha: new FormControl<string | null>(new Date().toISOString().slice(0, 10)),
    items: new FormArray<Renglon>([this.nuevoRenglon()]),
  });

  private readonly valores = toSignal(this.form.valueChanges, { initialValue: this.form.value });
  readonly total = computed(() =>
    (this.valores().items ?? []).reduce((suma, it) => suma + (Number(it?.costo) || 0), 0),
  );

  get items() {
    return this.form.controls.items;
  }

  agregar(): void {
    this.items.push(this.nuevoRenglon());
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
      .registrarCompras({
        proveedorId: v.proveedorId!,
        fecha: v.fecha || null,
        items: v.items.map((i) => ({ insumoId: i.insumoId!, cantidad: Number(i.cantidad), costo: Number(i.costo) })),
      })
      .subscribe({
        next: (compras) => this.ref.close(compras),
        error: (err) => {
          this.guardando.set(false);
          this.error.set(mensajeDeError(err));
        },
      });
  }

  private nuevoRenglon(): Renglon {
    return new FormGroup({
      insumoId: new FormControl<number | null>(null, Validators.required),
      cantidad: new FormControl<number | null>(1, [Validators.required, Validators.min(1)]),
      costo: new FormControl<number | null>(null, [Validators.required, Validators.min(0)]),
    });
  }
}
