import { Component, inject, signal } from '@angular/core';
import { FormControl, FormGroup, ReactiveFormsModule, ValidatorFn, Validators } from '@angular/forms';
import { MatButtonModule } from '@angular/material/button';
import { MAT_DIALOG_DATA, MatDialogModule, MatDialogRef } from '@angular/material/dialog';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';
import { MatProgressSpinnerModule } from '@angular/material/progress-spinner';
import { MatSelectModule } from '@angular/material/select';
import { Observable } from 'rxjs';
import { erroresDeCampos, mensajeDeError } from '../core/notificacion.service';

export interface CampoFormulario {
  clave: string;
  etiqueta: string;
  tipo: 'texto' | 'numero' | 'entero' | 'select' | 'fecha' | 'telefono';
  requerido?: boolean;
  min?: number;
  max?: number;
  maxLength?: number;
  prefijo?: string;
  sufijo?: string;
  ayuda?: string;
  opciones?: { valor: number | string; texto: string }[];
  /** Ocupa toda la fila del formulario. */
  ancho?: boolean;
}

export interface FormDialogData<T> {
  titulo: string;
  campos: CampoFormulario[];
  valores?: Partial<Record<string, unknown>>;
  textoGuardar?: string;
  /** Función que envía los datos al backend. El modal se cierra con su resultado. */
  guardar: (valores: Record<string, unknown>) => Observable<T>;
}

/**
 * Modal de alta / edición genérico construido a partir de una lista de campos.
 * Muestra los errores de validación que devuelve el backend en cada campo.
 */
@Component({
  selector: 'app-form-dialog',
  imports: [
    ReactiveFormsModule, MatDialogModule, MatFormFieldModule, MatInputModule, MatSelectModule,
    MatButtonModule, MatProgressSpinnerModule,
  ],
  template: `
    <h2 mat-dialog-title>{{ data.titulo }}</h2>
    <form [formGroup]="form" (ngSubmit)="enviar()">
      <mat-dialog-content>
        <div class="form-grid">
          @for (campo of data.campos; track campo.clave) {
            <mat-form-field appearance="outline" [class.ancho]="campo.ancho">
              <mat-label>{{ campo.etiqueta }}</mat-label>
              @switch (campo.tipo) {
                @case ('select') {
                  <mat-select [formControlName]="campo.clave">
                    @if (!campo.requerido) {
                      <mat-option [value]="null">— Ninguna —</mat-option>
                    }
                    @for (op of campo.opciones ?? []; track op.valor) {
                      <mat-option [value]="op.valor">{{ op.texto }}</mat-option>
                    }
                  </mat-select>
                }
                @case ('numero') {
                  <input matInput type="number" inputmode="decimal" step="0.01" [formControlName]="campo.clave" />
                }
                @case ('entero') {
                  <input matInput type="number" inputmode="numeric" step="1" [formControlName]="campo.clave" />
                }
                @case ('fecha') {
                  <input matInput type="date" [formControlName]="campo.clave" />
                }
                @case ('telefono') {
                  <input matInput type="tel" autocomplete="off" [formControlName]="campo.clave" />
                }
                @default {
                  <input matInput type="text" autocomplete="off" [formControlName]="campo.clave" />
                }
              }
              @if (campo.prefijo) {
                <span matTextPrefix>{{ campo.prefijo }}&nbsp;</span>
              }
              @if (campo.sufijo) {
                <span matTextSuffix>{{ campo.sufijo }}</span>
              }
              @if (campo.ayuda) {
                <mat-hint>{{ campo.ayuda }}</mat-hint>
              }
              <mat-error>{{ error(campo) }}</mat-error>
            </mat-form-field>
          }
        </div>
        @if (errorGeneral()) {
          <p class="error-general" role="alert">{{ errorGeneral() }}</p>
        }
      </mat-dialog-content>
      <mat-dialog-actions align="end">
        <button mat-button type="button" mat-dialog-close [disabled]="guardando()">Cancelar</button>
        <button mat-flat-button type="submit" [disabled]="guardando()">
          @if (guardando()) {
            <mat-spinner diameter="18" />
          } @else {
            {{ data.textoGuardar ?? 'Guardar' }}
          }
        </button>
      </mat-dialog-actions>
    </form>
  `,
  styles: `
    .ancho { grid-column: 1 / -1; }
    .error-general { color: var(--mat-sys-error); margin: 0; }
    mat-spinner { display: inline-block; }
  `,
})
export class FormDialog<T> {
  readonly data = inject<FormDialogData<T>>(MAT_DIALOG_DATA);
  private readonly ref = inject(MatDialogRef<FormDialog<T>, T>);

  readonly guardando = signal(false);
  readonly errorGeneral = signal('');
  readonly form = new FormGroup<Record<string, FormControl>>({});

  constructor() {
    for (const campo of this.data.campos) {
      const validadores: ValidatorFn[] = [];
      if (campo.requerido) validadores.push(Validators.required);
      if (campo.min !== undefined) validadores.push(Validators.min(campo.min));
      if (campo.max !== undefined) validadores.push(Validators.max(campo.max));
      if (campo.maxLength) validadores.push(Validators.maxLength(campo.maxLength));
      const inicial = this.data.valores?.[campo.clave] ?? (campo.tipo === 'select' ? null : '');
      this.form.addControl(campo.clave, new FormControl(inicial, validadores));
    }
  }

  error(campo: CampoFormulario): string {
    const c = this.form.controls[campo.clave];
    if (c.hasError('required')) return 'Este campo es obligatorio.';
    if (c.hasError('min')) return `Debe ser mayor o igual a ${campo.min}.`;
    if (c.hasError('max')) return `Debe ser menor o igual a ${campo.max}.`;
    if (c.hasError('maxlength')) return `Máximo ${campo.maxLength} caracteres.`;
    return c.getError('servidor') ?? '';
  }

  enviar(): void {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }
    this.guardando.set(true);
    this.errorGeneral.set('');

    this.data.guardar(this.normalizar()).subscribe({
      next: (resultado) => this.ref.close(resultado),
      error: (err) => {
        this.guardando.set(false);
        const campos = erroresDeCampos(err);
        let asignado = false;
        for (const [clave, mensaje] of Object.entries(campos)) {
          const control = this.form.controls[clave];
          if (control) {
            control.setErrors({ servidor: mensaje });
            control.markAsTouched();
            asignado = true;
          }
        }
        if (!asignado) {
          this.errorGeneral.set(mensajeDeError(err));
        }
      },
    });
  }

  /** Convierte los valores a los tipos que espera la API. */
  private normalizar(): Record<string, unknown> {
    const salida: Record<string, unknown> = {};
    for (const campo of this.data.campos) {
      const valor = this.form.controls[campo.clave].value;
      if (campo.tipo === 'numero' || campo.tipo === 'entero') {
        salida[campo.clave] = valor === '' || valor === null ? null : Number(valor);
      } else if (typeof valor === 'string') {
        salida[campo.clave] = valor.trim();
      } else {
        salida[campo.clave] = valor;
      }
    }
    return salida;
  }
}
