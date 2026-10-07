import { Component, inject, signal } from '@angular/core';
import { AbstractControl, FormControl, FormGroup, ReactiveFormsModule, ValidationErrors, Validators } from '@angular/forms';
import { MatButtonModule } from '@angular/material/button';
import { MatDialogModule, MatDialogRef } from '@angular/material/dialog';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';
import { AuthService } from '../core/auth.service';
import { mensajeDeError } from '../core/notificacion.service';

const coinciden = (g: AbstractControl): ValidationErrors | null =>
  g.get('nueva')?.value === g.get('repetir')?.value ? null : { noCoinciden: true };

@Component({
  selector: 'app-cambiar-password-dialog',
  imports: [ReactiveFormsModule, MatDialogModule, MatFormFieldModule, MatInputModule, MatButtonModule],
  template: `
    <h2 mat-dialog-title>Cambiar contraseña</h2>
    <form [formGroup]="form" (ngSubmit)="guardar()">
      <mat-dialog-content>
        <mat-form-field appearance="outline" class="full-width">
          <mat-label>Contraseña actual</mat-label>
          <input matInput type="password" formControlName="actual" autocomplete="current-password" />
        </mat-form-field>
        <mat-form-field appearance="outline" class="full-width">
          <mat-label>Nueva contraseña</mat-label>
          <input matInput type="password" formControlName="nueva" autocomplete="new-password" />
          <mat-hint>Mínimo 12 caracteres. Conviene una frase larga.</mat-hint>
          <mat-error>Mínimo 12 caracteres.</mat-error>
        </mat-form-field>
        <mat-form-field appearance="outline" class="full-width">
          <mat-label>Repetir nueva contraseña</mat-label>
          <input matInput type="password" formControlName="repetir" autocomplete="new-password" />
        </mat-form-field>
        @if (form.hasError('noCoinciden') && form.controls.repetir.touched) {
          <p class="error">Las contraseñas no coinciden.</p>
        }
        @if (error()) {
          <p class="error" role="alert">{{ error() }}</p>
        }
      </mat-dialog-content>
      <mat-dialog-actions align="end">
        <button mat-button type="button" mat-dialog-close>Cancelar</button>
        <button mat-flat-button type="submit" [disabled]="guardando()">Guardar</button>
      </mat-dialog-actions>
    </form>
  `,
  styles: `.error { color: var(--mat-sys-error); margin: 0; }`,
})
export class CambiarPasswordDialog {
  private readonly auth = inject(AuthService);
  private readonly ref = inject(MatDialogRef<CambiarPasswordDialog, boolean>);
  readonly guardando = signal(false);
  readonly error = signal('');

  readonly form = new FormGroup(
    {
      actual: new FormControl('', { nonNullable: true, validators: Validators.required }),
      nueva: new FormControl('', { nonNullable: true, validators: [Validators.required, Validators.minLength(12)] }),
      repetir: new FormControl('', { nonNullable: true, validators: Validators.required }),
    },
    { validators: coinciden },
  );

  guardar(): void {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }
    this.guardando.set(true);
    const { actual, nueva } = this.form.getRawValue();
    this.auth.cambiarPassword(actual, nueva).subscribe({
      next: () => this.ref.close(true),
      error: (err) => {
        this.guardando.set(false);
        this.error.set(mensajeDeError(err));
      },
    });
  }
}
