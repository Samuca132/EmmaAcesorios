import { CurrencyPipe } from '@angular/common';
import { Component, computed, inject, signal } from '@angular/core';
import { toSignal } from '@angular/core/rxjs-interop';
import { FormArray, FormControl, FormGroup, ReactiveFormsModule, Validators } from '@angular/forms';
import { MatButtonModule } from '@angular/material/button';
import { MAT_DIALOG_DATA, MatDialogModule, MatDialogRef } from '@angular/material/dialog';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatIconModule } from '@angular/material/icon';
import { MatInputModule } from '@angular/material/input';
import { MatProgressBarModule } from '@angular/material/progress-bar';
import { MatProgressSpinnerModule } from '@angular/material/progress-spinner';
import { MatSelectModule } from '@angular/material/select';
import { MatTooltipModule } from '@angular/material/tooltip';
import { ApiService, sinError } from '../../core/api.service';
import { Composicion, Insumo, Producto } from '../../core/models';
import { erroresDeCampos, mensajeDeError } from '../../core/notificacion.service';

type Renglon = FormGroup<{ insumoId: FormControl<number | null>; cantidad: FormControl<number | null> }>;

/**
 * Qué insumos lleva cada unidad del producto. Un producto de reventa lleva
 * su propio insumo × 1; uno fabricado, varios. Sin composición no se puede
 * pasar a venta.
 */
@Component({
  selector: 'app-composicion-dialog',
  imports: [
    ReactiveFormsModule, MatDialogModule, MatFormFieldModule, MatInputModule, MatSelectModule, MatButtonModule,
    MatIconModule, MatTooltipModule, MatProgressBarModule, MatProgressSpinnerModule, CurrencyPipe,
  ],
  template: `
    <h2 mat-dialog-title>Composición de "{{ producto.nombre }}"</h2>
    <form [formGroup]="form" (ngSubmit)="guardar()">
      <mat-dialog-content>
        <p class="text-muted ayuda">
          Qué insumos lleva <strong>cada unidad</strong>. Si lo comprás para revender, poné su mismo artículo
          de insumos × 1. Al pasarlo a venta se descuentan estos insumos y el coste se calcula solo.
        </p>

        @if (cargando()) {
          <mat-progress-bar mode="indeterminate" />
        } @else {
          <div formArrayName="componentes">
            @for (renglon of componentes.controls; track renglon; let i = $index) {
              <div class="renglon" [formGroupName]="i">
                <mat-form-field appearance="outline" class="insumo">
                  <mat-label>Insumo</mat-label>
                  <mat-select formControlName="insumoId">
                    @for (ins of insumos(); track ins.id) {
                      <mat-option [value]="ins.id">
                        {{ ins.nombre }} · {{ ins.costoPromedio | currency: 'ARS' : 'symbol-narrow' : '1.2-2' : 'es-AR' }} · stock {{ ins.stock }}
                      </mat-option>
                    }
                  </mat-select>
                  @if (borrado(renglon)) {
                    <mat-hint class="error">Este insumo fue dado de baja: cambialo o quitalo.</mat-hint>
                  }
                  <mat-error>{{ renglon.controls.insumoId.getError('servidor') ?? 'Elegí un insumo.' }}</mat-error>
                </mat-form-field>
                <mat-form-field appearance="outline" class="cantidad">
                  <mat-label>Por unidad</mat-label>
                  <input matInput type="number" min="1" step="1" inputmode="numeric" formControlName="cantidad" />
                  <mat-error>Mínimo 1.</mat-error>
                </mat-form-field>
                <button mat-icon-button type="button" matTooltip="Quitar" (click)="componentes.removeAt(i)">
                  <mat-icon>remove_circle_outline</mat-icon>
                </button>
              </div>
            }
          </div>
          <button mat-button type="button" (click)="agregar()"><mat-icon>add</mat-icon>Agregar insumo</button>

          <mat-form-field appearance="outline" class="adicional">
            <mat-label>Costo adicional por unidad</mat-label>
            <span matTextPrefix>$&nbsp;</span>
            <input matInput type="number" min="0" step="0.01" inputmode="decimal" formControlName="costoAdicional" />
            <mat-hint>Mano de obra, packaging… (opcional)</mat-hint>
          </mat-form-field>

          <p class="costo">
            Costo por unidad con el costo actual de los insumos:
            <strong>{{ costoPorUnidad() | currency: 'ARS' : 'symbol-narrow' : '1.2-2' : 'es-AR' }}</strong>
          </p>
        }
        @if (error()) {
          <p class="error" role="alert">{{ error() }}</p>
        }
      </mat-dialog-content>
      <mat-dialog-actions align="end">
        <button mat-button type="button" mat-dialog-close [disabled]="guardando()">Cancelar</button>
        <button mat-flat-button type="submit" [disabled]="guardando() || cargando()">
          @if (guardando()) { <mat-spinner diameter="18" /> } @else { Guardar }
        </button>
      </mat-dialog-actions>
    </form>
  `,
  styles: `
    .ayuda { margin-top: 0; }
    .renglon { display: flex; gap: 8px; align-items: flex-start; margin-bottom: 8px; }
    .insumo { flex: 1 1 auto; min-width: 0; }
    .cantidad { width: 120px; flex: 0 0 auto; }
    .adicional { width: 100%; margin-top: 12px; }
    .costo { text-align: right; font: var(--mat-sys-title-small); }
    .error { color: var(--mat-sys-error); }
    mat-spinner { display: inline-block; }
  `,
})
export class ComposicionDialog {
  private readonly api = inject(ApiService);
  private readonly ref = inject(MatDialogRef<ComposicionDialog, Composicion>);
  readonly producto = inject<Producto>(MAT_DIALOG_DATA);

  readonly insumos = toSignal(sinError(this.api.insumos()), { initialValue: [] as Insumo[] });
  readonly cargando = signal(true);
  readonly guardando = signal(false);
  readonly error = signal('');
  private readonly borrados = signal<number[]>([]);

  readonly form = new FormGroup({
    componentes: new FormArray<Renglon>([]),
    costoAdicional: new FormControl<number | null>(0, Validators.min(0)),
  });
  private readonly valores = toSignal(this.form.valueChanges, { initialValue: this.form.value });

  readonly costoPorUnidad = computed(() => {
    const v = this.valores();
    const insumos = this.insumos();
    return (v.componentes ?? []).reduce((suma, c) => {
      const ins = insumos.find((i) => i.id === c?.insumoId);
      return suma + (ins ? ins.costoPromedio * (Number(c?.cantidad) || 0) : 0);
    }, Number(v.costoAdicional) || 0);
  });

  get componentes() {
    return this.form.controls.componentes;
  }

  constructor() {
    this.api.composicion(this.producto.id).subscribe({
      next: (c) => {
        for (const comp of c.componentes) {
          this.componentes.push(this.renglon(comp.insumoId, comp.cantidad));
        }
        if (!c.componentes.length) this.agregar();
        this.borrados.set(c.componentes.filter((x) => x.insumoBorrado).map((x) => x.insumoId));
        this.form.controls.costoAdicional.setValue(c.costoAdicional);
        this.cargando.set(false);
      },
      error: (err) => {
        this.cargando.set(false);
        this.error.set(mensajeDeError(err));
      },
    });
  }

  agregar(): void {
    this.componentes.push(this.renglon(null, 1));
  }

  borrado(renglon: Renglon): boolean {
    return this.borrados().includes(renglon.controls.insumoId.value ?? 0);
  }

  guardar(): void {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }
    this.guardando.set(true);
    this.error.set('');
    const v = this.form.getRawValue();
    this.api
      .guardarComposicion(this.producto.id, {
        costoAdicional: Number(v.costoAdicional) || 0,
        componentes: v.componentes.map((c) => ({ insumoId: c.insumoId!, cantidad: Number(c.cantidad) })),
      })
      .subscribe({
        next: (c) => this.ref.close(c),
        error: (err) => {
          this.guardando.set(false);
          // "componentes.1.insumoId" → error en ese renglón
          let asignado = false;
          for (const [campo, mensaje] of Object.entries(erroresDeCampos(err))) {
            const m = /^componentes\.(\d+)\.(insumoId|cantidad)$/.exec(campo);
            const control = m ? this.componentes.at(Number(m[1]))?.get(m[2]) : null;
            if (control) {
              control.setErrors({ servidor: mensaje });
              control.markAsTouched();
              asignado = true;
            }
          }
          if (!asignado) this.error.set(mensajeDeError(err));
        },
      });
  }

  private renglon(insumoId: number | null, cantidad: number): Renglon {
    return new FormGroup({
      insumoId: new FormControl<number | null>(insumoId, Validators.required),
      cantidad: new FormControl<number | null>(cantidad, [Validators.required, Validators.min(1)]),
    });
  }
}
