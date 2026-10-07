import { Component, inject, signal } from '@angular/core';
import { toSignal } from '@angular/core/rxjs-interop';
import { FormArray, FormControl, FormGroup, ReactiveFormsModule, Validators } from '@angular/forms';
import { MatButtonModule } from '@angular/material/button';
import { MatDialogModule, MatDialogRef } from '@angular/material/dialog';
import { MatDividerModule } from '@angular/material/divider';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatIconModule } from '@angular/material/icon';
import { MatInputModule } from '@angular/material/input';
import { MatProgressSpinnerModule } from '@angular/material/progress-spinner';
import { MatSelectModule } from '@angular/material/select';
import { MatTooltipModule } from '@angular/material/tooltip';
import { ApiService, sinError } from '../../core/api.service';
import { Canje, Insumo, Producto, Proveedor } from '../../core/models';
import { mensajeDeError } from '../../core/notificacion.service';

type Renglon = FormGroup<{
  productoId: FormControl<number | null>;
  cantidadProducto: FormControl<number | null>;
  insumoId: FormControl<number | null>;
  cantidadInsumo: FormControl<number | null>;
}>;

/** Modal "Nuevo canje": un proveedor y uno o más intercambios producto → insumo. */
@Component({
  selector: 'app-canje-dialog',
  imports: [
    ReactiveFormsModule, MatDialogModule, MatFormFieldModule, MatInputModule, MatSelectModule,
    MatButtonModule, MatIconModule, MatTooltipModule, MatProgressSpinnerModule, MatDividerModule,
  ],
  template: `
    <h2 mat-dialog-title>Nuevo canje</h2>
    <form [formGroup]="form" (ngSubmit)="enviar()">
      <mat-dialog-content>
        <div class="fila">
          <mat-form-field appearance="outline" class="ancho">
            <mat-label>Proveedor</mat-label>
            <mat-select formControlName="proveedorId">
              @for (p of proveedores(); track p.id) {
                <mat-option [value]="p.id">{{ p.nombre }}</mat-option>
              }
            </mat-select>
            <mat-error>Elegí un proveedor.</mat-error>
          </mat-form-field>
          <mat-form-field appearance="outline" class="desc">
            <mat-label>% desc. productos</mat-label>
            <input matInput type="number" min="0" max="100" formControlName="descuentoProducto" />
          </mat-form-field>
          <mat-form-field appearance="outline" class="desc">
            <mat-label>% desc. insumos</mat-label>
            <input matInput type="number" min="0" max="100" formControlName="descuentoInsumo" />
          </mat-form-field>
        </div>

        <div formArrayName="items">
          @for (renglon of items.controls; track renglon; let i = $index) {
            <mat-divider />
            <div class="renglon" [formGroupName]="i">
              <span class="numero-renglon">{{ i + 1 }}</span>
              <div class="campos">
                <div class="fila">
                  <mat-form-field appearance="outline" class="ancho">
                    <mat-label>Producto que entregás</mat-label>
                    <mat-select formControlName="productoId">
                      @for (p of productos(); track p.id) {
                        <mat-option [value]="p.id" [disabled]="p.stock === 0">{{ p.nombre }} · stock {{ p.stock }}</mat-option>
                      }
                    </mat-select>
                    <mat-error>Elegí un producto.</mat-error>
                  </mat-form-field>
                  <mat-form-field appearance="outline" class="cant">
                    <mat-label>Cantidad</mat-label>
                    <input matInput type="number" min="1" step="1" formControlName="cantidadProducto" />
                    <mat-error>Mínimo 1.</mat-error>
                  </mat-form-field>
                </div>
                <div class="fila">
                  <mat-form-field appearance="outline" class="ancho">
                    <mat-label>Insumo que recibís</mat-label>
                    <mat-select formControlName="insumoId">
                      @for (ins of insumos(); track ins.id) {
                        <mat-option [value]="ins.id">{{ ins.nombre }}</mat-option>
                      }
                    </mat-select>
                    <mat-error>Elegí un insumo.</mat-error>
                  </mat-form-field>
                  <mat-form-field appearance="outline" class="cant">
                    <mat-label>Cantidad</mat-label>
                    <input matInput type="number" min="1" step="1" formControlName="cantidadInsumo" />
                    <mat-error>Mínimo 1.</mat-error>
                  </mat-form-field>
                </div>
              </div>
              <button mat-icon-button type="button" matTooltip="Quitar" [disabled]="items.length === 1"
                      (click)="items.removeAt(i)">
                <mat-icon>remove_circle_outline</mat-icon>
              </button>
            </div>
          }
        </div>

        <button mat-button type="button" (click)="agregar()"><mat-icon>add</mat-icon>Agregar intercambio</button>
        @if (error()) {
          <p class="error" role="alert">{{ error() }}</p>
        }
      </mat-dialog-content>
      <mat-dialog-actions align="end">
        <button mat-button type="button" mat-dialog-close [disabled]="guardando()">Cancelar</button>
        <button mat-flat-button type="submit" [disabled]="guardando()">
          @if (guardando()) { <mat-spinner diameter="18" /> } @else { Registrar canje }
        </button>
      </mat-dialog-actions>
    </form>
  `,
  styles: `
    .fila { display: flex; gap: 8px; align-items: flex-start; flex-wrap: wrap; }
    .ancho { flex: 1 1 220px; min-width: 0; }
    .desc { flex: 0 1 160px; min-width: 140px; }
    .cant { flex: 0 1 120px; min-width: 100px; }
    .renglon { display: flex; gap: 8px; align-items: flex-start; padding-top: 16px; }
    .campos { flex: 1 1 auto; min-width: 0; }
    .numero-renglon {
      flex: 0 0 28px; height: 28px; margin-top: 14px; border-radius: 50%; display: grid; place-items: center;
      font: var(--mat-sys-label-large); background: var(--mat-sys-secondary-container); color: var(--mat-sys-on-secondary-container);
    }
    .error { color: var(--mat-sys-error); }
    mat-spinner { display: inline-block; }
  `,
})
export class CanjeDialog {
  private readonly api = inject(ApiService);
  private readonly ref = inject(MatDialogRef<CanjeDialog, Canje[]>);

  readonly proveedores = toSignal(sinError(this.api.proveedores()), { initialValue: [] as Proveedor[] });
  readonly productos = toSignal(sinError(this.api.productos()), { initialValue: [] as Producto[] });
  readonly insumos = toSignal(sinError(this.api.insumos()), { initialValue: [] as Insumo[] });
  readonly guardando = signal(false);
  readonly error = signal('');

  readonly form = new FormGroup({
    proveedorId: new FormControl<number | null>(null, Validators.required),
    descuentoProducto: new FormControl<number | null>(null, [Validators.min(0), Validators.max(100)]),
    descuentoInsumo: new FormControl<number | null>(null, [Validators.min(0), Validators.max(100)]),
    items: new FormArray<Renglon>([this.nuevoRenglon()]),
  });

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
    const numero = (x: number | null) => (x === null || (x as unknown) === '' ? null : Number(x));
    this.api
      .registrarCanjes({
        proveedorId: v.proveedorId!,
        descuentoProducto: numero(v.descuentoProducto),
        descuentoInsumo: numero(v.descuentoInsumo),
        items: v.items.map((i) => ({
          productoId: i.productoId!,
          cantidadProducto: Number(i.cantidadProducto),
          insumoId: i.insumoId!,
          cantidadInsumo: Number(i.cantidadInsumo),
        })),
      })
      .subscribe({
        next: (canjes) => this.ref.close(canjes),
        error: (err) => {
          this.guardando.set(false);
          this.error.set(mensajeDeError(err));
        },
      });
  }

  private nuevoRenglon(): Renglon {
    return new FormGroup({
      productoId: new FormControl<number | null>(null, Validators.required),
      cantidadProducto: new FormControl<number | null>(1, [Validators.required, Validators.min(1)]),
      insumoId: new FormControl<number | null>(null, Validators.required),
      cantidadInsumo: new FormControl<number | null>(1, [Validators.required, Validators.min(1)]),
    });
  }
}
