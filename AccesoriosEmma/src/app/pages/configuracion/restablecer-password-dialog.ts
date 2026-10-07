import { Component, inject, signal } from '@angular/core';
import { FormControl, FormGroup, ReactiveFormsModule, Validators } from '@angular/forms';
import { MatButtonModule } from '@angular/material/button';
import { MAT_DIALOG_DATA, MatDialogModule, MatDialogRef } from '@angular/material/dialog';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatIconModule } from '@angular/material/icon';
import { MatInputModule } from '@angular/material/input';
import { ApiService } from '../../core/api.service';
import { UsuarioAdmin } from '../../core/models';
import { erroresDeCampos, mensajeDeError } from '../../core/notificacion.service';
import { LARGO_MINIMO_PASSWORD, generarPassword, passwordsCoinciden } from './password';

/** Un administrador le pone una contraseña nueva a otro usuario (y lo desbloquea). */
@Component({
  selector: 'app-restablecer-password-dialog',
  imports: [ReactiveFormsModule, MatDialogModule, MatFormFieldModule, MatInputModule, MatButtonModule, MatIconModule],
  template: `
    <h2 mat-dialog-title>Restablecer contraseña</h2>
    <form [formGroup]="form" (ngSubmit)="guardar()">
      <mat-dialog-content>
        <p class="text-muted">
          Nueva contraseña para <strong>{{ usuario.nombre }}</strong> ({{ usuario.email }}). Si la cuenta estaba
          bloqueada por intentos fallidos, también se desbloquea.
        </p>
        <mat-form-field class="full-width">
          <mat-label>Nueva contraseña</mat-label>
          <input matInput [type]="ver() ? 'text' : 'password'" formControlName="password" autocomplete="new-password" />
          <button mat-icon-button matSuffix type="button" (click)="ver.set(!ver())"
                  [attr.aria-label]="ver() ? 'Ocultar contraseña' : 'Mostrar contraseña'">
            <mat-icon>{{ ver() ? 'visibility_off' : 'visibility' }}</mat-icon>
          </button>
          <mat-hint>Mínimo {{ largoMinimo }} caracteres.</mat-hint>
          <mat-error>{{ errorPassword() }}</mat-error>
        </mat-form-field>
        <mat-form-field class="full-width">
          <mat-label>Repetir contraseña</mat-label>
          <input matInput [type]="ver() ? 'text' : 'password'" formControlName="repetir" autocomplete="new-password" />
          @if (form.hasError('noCoinciden') && form.controls.repetir.touched) {
            <mat-hint class="error">Las contraseñas no coinciden.</mat-hint>
          }
        </mat-form-field>
        <button mat-button type="button" (click)="generar()"><mat-icon>key</mat-icon>Generar contraseña segura</button>
        @if (generada()) {
          <p class="aviso">Copiala y pasásela al usuario de forma segura: después no se vuelve a mostrar.</p>
        }
        @if (error()) {
          <p class="error" role="alert">{{ error() }}</p>
        }
      </mat-dialog-content>
      <mat-dialog-actions align="end">
        <button mat-button type="button" mat-dialog-close [disabled]="guardando()">Cancelar</button>
        <button mat-flat-button type="submit" [disabled]="guardando()">Guardar contraseña</button>
      </mat-dialog-actions>
    </form>
  `,
  styles: `
    .error { color: var(--mat-sys-error); }
    .aviso { padding: 8px 12px; border-radius: 8px; background: var(--mat-sys-tertiary-container); color: var(--mat-sys-on-tertiary-container); }
  `,
})
export class RestablecerPasswordDialog {
  private readonly api = inject(ApiService);
  private readonly ref = inject(MatDialogRef<RestablecerPasswordDialog, UsuarioAdmin>);
  readonly usuario = inject<UsuarioAdmin>(MAT_DIALOG_DATA);
  readonly largoMinimo = LARGO_MINIMO_PASSWORD;

  readonly ver = signal(false);
  readonly generada = signal(false);
  readonly guardando = signal(false);
  readonly error = signal('');

  readonly form = new FormGroup(
    {
      password: new FormControl('', {
        nonNullable: true,
        validators: [Validators.required, Validators.minLength(LARGO_MINIMO_PASSWORD)],
      }),
      repetir: new FormControl('', { nonNullable: true, validators: Validators.required }),
    },
    { validators: passwordsCoinciden },
  );

  errorPassword(): string {
    const c = this.form.controls.password;
    if (c.hasError('required')) return 'Este campo es obligatorio.';
    if (c.hasError('minlength')) return `Mínimo ${LARGO_MINIMO_PASSWORD} caracteres.`;
    return c.getError('servidor') ?? '';
  }

  generar(): void {
    const password = generarPassword();
    this.form.patchValue({ password, repetir: password });
    this.ver.set(true);
    this.generada.set(true);
  }

  guardar(): void {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }
    this.guardando.set(true);
    this.error.set('');
    this.api.restablecerPassword(this.usuario.id, this.form.getRawValue().password).subscribe({
      next: (u) => this.ref.close(u),
      error: (err) => {
        this.guardando.set(false);
        const campos = erroresDeCampos(err);
        if (campos['password']) {
          this.form.controls.password.setErrors({ servidor: campos['password'] });
        } else {
          this.error.set(mensajeDeError(err));
        }
      },
    });
  }
}
