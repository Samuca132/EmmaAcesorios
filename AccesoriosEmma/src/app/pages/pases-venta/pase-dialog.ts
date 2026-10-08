import { CurrencyPipe } from '@angular/common';
import { Component, inject, signal } from '@angular/core';
import { takeUntilDestroyed, toSignal } from '@angular/core/rxjs-interop';
import { FormArray, FormControl, FormGroup, ReactiveFormsModule, Validators } from '@angular/forms';
import { MatButtonModule } from '@angular/material/button';
import { MatDialogModule, MatDialogRef } from '@angular/material/dialog';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatIconModule } from '@angular/material/icon';
import { MatInputModule } from '@angular/material/input';
import { MatProgressBarModule } from '@angular/material/progress-bar';
import { MatProgressSpinnerModule } from '@angular/material/progress-spinner';
import { MatSelectModule } from '@angular/material/select';
import { MatTooltipModule } from '@angular/material/tooltip';
import { catchError, debounceTime, filter, of, switchMap, tap } from 'rxjs';
import { ApiService, sinError } from '../../core/api.service';
import { PaseVenta, Producto, SimulacionPase } from '../../core/models';
import { mensajeDeError } from '../../core/notificacion.service';

type Renglon = FormGroup<{ productoId: FormControl<number | null>; cantidad: FormControl<number | null> }>;

/**
 * Nuevo pase a venta: productos y cantidades. Mientras se completa, el
 * backend simula el pase y se muestra qué insumos se van a consumir, si
 * alcanzan y cuánto cuesta.
 */
@Component({
  selector: 'app-pase-dialog',
  imports: [
    ReactiveFormsModule, MatDialogModule, MatFormFieldModule, MatInputModule, MatSelectModule, MatButtonModule,
    MatIconModule, MatTooltipModule, MatProgressBarModule, MatProgressSpinnerModule, CurrencyPipe,
  ],
  template: `
    <h2 mat-dialog-title>Pasar a venta</h2>
    <form [formGroup]="form" (ngSubmit)="enviar()">
      <mat-dialog-content>
        <p class="text-muted ayuda">
          Elegí qué productos pasan a estar a la venta. Se descuentan los insumos de su composición y el coste del
          producto se actualiza solo.
        </p>
        <div formArrayName="items">
          @for (renglon of items.controls; track renglon; let i = $index) {
            <div class="renglon" [formGroupName]="i">
              <mat-form-field appearance="outline" class="producto">
                <mat-label>Producto</mat-label>
                <mat-select formControlName="productoId">
                  @for (p of productos(); track p.id) {
                    <mat-option [value]="p.id" [disabled]="!p.tieneComposicion">
                      {{ p.nombre }} · stock {{ p.stock }}@if (!p.tieneComposicion) { · sin composición }
                    </mat-option>
                  }
                </mat-select>
                <mat-error>Elegí un producto.</mat-error>
              </mat-form-field>
              <mat-form-field appearance="outline" class="cantidad">
                <mat-label>Cantidad</mat-label>
                <input matInput type="number" min="1" step="1" inputmode="numeric" formControlName="cantidad" />
                <mat-error>Mínimo 1.</mat-error>
              </mat-form-field>
              <button mat-icon-button type="button" matTooltip="Quitar" [disabled]="items.length === 1" (click)="items.removeAt(i)">
                <mat-icon>remove_circle_outline</mat-icon>
              </button>
            </div>
          }
        </div>
        <button mat-button type="button" (click)="agregar()"><mat-icon>add</mat-icon>Agregar producto</button>

        <mat-form-field appearance="outline" class="nota">
          <mat-label>Nota (opcional)</mat-label>
          <input matInput formControlName="nota" maxlength="255" autocomplete="off" />
        </mat-form-field>

        @if (simulando()) { <mat-progress-bar mode="indeterminate" /> }
        @if (simulacion(); as s) {
          <section class="vista-previa">
            <h3>Se van a consumir</h3>
            <ul class="insumos">
              @for (n of s.insumos; track n.insumoId) {
                <li [class.falta]="!n.alcanza">
                  <mat-icon>{{ n.alcanza ? 'check_circle' : 'error' }}</mat-icon>
                  <span>{{ n.necesita }} × {{ n.insumo }}</span>
                  <span class="text-muted">hay {{ n.disponible }}</span>
                </li>
              }
            </ul>
            @for (p of s.problemas; track p) {
              <p class="error">{{ p }}</p>
            }
            <p class="costo">
              Costo total: <strong>{{ s.costoTotal | currency: 'ARS' : 'symbol-narrow' : '1.2-2' : 'es-AR' }}</strong>
              @for (i of s.items; track i.productoId) {
                <br /><span class="text-muted">{{ i.producto }}: {{ i.costoUnitario | currency: 'ARS' : 'symbol-narrow' : '1.2-2' : 'es-AR' }} c/u</span>
              }
            </p>
          </section>
        }
        @if (error()) {
          <p class="error" role="alert">{{ error() }}</p>
        }
      </mat-dialog-content>
      <mat-dialog-actions align="end">
        <button mat-button type="button" mat-dialog-close [disabled]="guardando()">Cancelar</button>
        <button mat-flat-button type="submit" [disabled]="guardando() || !!simulacion()?.problemas?.length">
          @if (guardando()) { <mat-spinner diameter="18" /> } @else { Pasar a venta }
        </button>
      </mat-dialog-actions>
    </form>
  `,
  styles: `
    .ayuda { margin-top: 0; }
    .renglon { display: flex; gap: 8px; align-items: flex-start; margin-bottom: 8px; }
    .producto { flex: 1 1 auto; min-width: 0; }
    .cantidad { width: 120px; flex: 0 0 auto; }
    .nota { width: 100%; margin-top: 8px; }
    .vista-previa { border: 1px solid var(--mat-sys-outline-variant); border-radius: 12px; padding: 4px 16px; }
    h3 { font: var(--mat-sys-title-small); margin: 12px 0 4px; }
    .insumos { list-style: none; padding: 0; margin: 0; }
    .insumos li { display: flex; align-items: center; gap: 8px; padding: 2px 0; }
    .insumos mat-icon { color: var(--mat-sys-primary); font-size: 20px; width: 20px; height: 20px; }
    .insumos .falta mat-icon, .error { color: var(--mat-sys-error); }
    .costo { text-align: right; }
    mat-spinner { display: inline-block; }
  `,
})
export class PaseDialog {
  private readonly api = inject(ApiService);
  private readonly ref = inject(MatDialogRef<PaseDialog, PaseVenta>);

  readonly productos = toSignal(sinError(this.api.productos()), { initialValue: [] as Producto[] });
  readonly simulacion = signal<SimulacionPase | null>(null);
  readonly simulando = signal(false);
  readonly guardando = signal(false);
  readonly error = signal('');

  readonly form = new FormGroup({
    items: new FormArray<Renglon>([this.nuevoRenglon()]),
    nota: new FormControl<string>('', { nonNullable: true }),
  });

  get items() {
    return this.form.controls.items;
  }

  constructor() {
    // Simula cada vez que cambian los renglones (si están completos)
    this.items.valueChanges
      .pipe(
        debounceTime(300),
        tap(() => this.items.invalid && this.simulacion.set(null)),
        filter(() => this.items.valid),
        tap(() => this.simulando.set(true)),
        switchMap(() => this.api.simularPase(this.renglones()).pipe(catchError(() => of(null)))),
        takeUntilDestroyed(),
      )
      .subscribe((s) => {
        this.simulando.set(false);
        this.simulacion.set(s);
      });
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
    this.api.registrarPase({ items: this.renglones(), nota: this.form.controls.nota.value.trim() || null }).subscribe({
      next: (pase) => this.ref.close(pase),
      error: (err) => {
        this.guardando.set(false);
        this.error.set(mensajeDeError(err));
      },
    });
  }

  private renglones() {
    return this.items.getRawValue().map((r) => ({ productoId: r.productoId!, cantidad: Number(r.cantidad) }));
  }

  private nuevoRenglon(): Renglon {
    return new FormGroup({
      productoId: new FormControl<number | null>(null, Validators.required),
      cantidad: new FormControl<number | null>(1, [Validators.required, Validators.min(1)]),
    });
  }
}
