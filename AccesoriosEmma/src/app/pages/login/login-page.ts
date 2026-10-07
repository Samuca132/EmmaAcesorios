import { Component, inject, input, signal } from '@angular/core';
import { FormControl, FormGroup, ReactiveFormsModule, Validators } from '@angular/forms';
import { MatButtonModule } from '@angular/material/button';
import { MatCardModule } from '@angular/material/card';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatIconModule } from '@angular/material/icon';
import { MatInputModule } from '@angular/material/input';
import { MatProgressBarModule } from '@angular/material/progress-bar';
import { Router } from '@angular/router';
import { AuthService } from '../../core/auth.service';
import { mensajeDeError } from '../../core/notificacion.service';

@Component({
  selector: 'app-login-page',
  imports: [
    ReactiveFormsModule, MatCardModule, MatFormFieldModule, MatInputModule, MatButtonModule, MatIconModule,
    MatProgressBarModule,
  ],
  template: `
    <main class="fondo">
      <mat-card appearance="outlined" class="tarjeta">
        @if (cargando()) {
          <mat-progress-bar mode="indeterminate" class="progreso" />
        }
        <mat-card-content>
          <img src="logoEmma.png" alt="Emma Accesorios" class="logo" width="140" height="140" />
          <h1>Iniciar sesión</h1>

          @if (motivo() === 'expirada' && !error()) {
            <p class="aviso" role="status">Tu sesión venció. Volvé a ingresar.</p>
          }

          <form [formGroup]="form" (ngSubmit)="ingresar()">
            <mat-form-field appearance="outline" class="full-width">
              <mat-label>Email</mat-label>
              <mat-icon matPrefix>mail</mat-icon>
              <input matInput type="email" formControlName="email" autocomplete="username" />
              @if (form.controls.email.hasError('email')) {
                <mat-error>Ingresá un email válido.</mat-error>
              } @else {
                <mat-error>Ingresá tu email.</mat-error>
              }
            </mat-form-field>

            <mat-form-field appearance="outline" class="full-width">
              <mat-label>Contraseña</mat-label>
              <mat-icon matPrefix>lock</mat-icon>
              <input matInput [type]="verPassword() ? 'text' : 'password'" formControlName="password"
                     autocomplete="current-password" />
              <button mat-icon-button matSuffix type="button" (click)="verPassword.set(!verPassword())"
                      [attr.aria-label]="verPassword() ? 'Ocultar contraseña' : 'Mostrar contraseña'">
                <mat-icon>{{ verPassword() ? 'visibility_off' : 'visibility' }}</mat-icon>
              </button>
              <mat-error>Ingresá tu contraseña.</mat-error>
            </mat-form-field>

            @if (error()) {
              <p class="error" role="alert"><mat-icon>error</mat-icon>{{ error() }}</p>
            }

            <button mat-flat-button type="submit" class="full-width ingresar" [disabled]="cargando()">Ingresar</button>
          </form>
          <p class="text-muted ayuda">¿Olvidaste la contraseña? Pedile a quien administra el sistema que te genere una nueva.</p>
        </mat-card-content>
      </mat-card>
    </main>
  `,
  styles: `
    .fondo {
      min-height: 100vh; display: grid; place-items: center; padding: 16px; box-sizing: border-box;
      background: linear-gradient(160deg, var(--mat-sys-primary-container), var(--mat-sys-tertiary-container));
    }
    .tarjeta { width: 100%; max-width: 400px; position: relative; overflow: hidden; background: var(--mat-sys-surface); }
    .progreso { position: absolute; top: 0; left: 0; right: 0; }
    mat-card-content { padding: 32px 24px 16px !important; display: flex; flex-direction: column; align-items: center; }
    form { width: 100%; }
    .logo { margin-bottom: 8px; }
    h1 { font: var(--mat-sys-headline-small); margin: 0 0 24px; }
    .ingresar { height: 48px; margin-top: 8px; }
    .error { display: flex; align-items: center; gap: 8px; color: var(--mat-sys-error); margin: 0 0 8px; }
    .aviso { color: var(--mat-sys-on-surface-variant); margin: 0 0 16px; }
    .ayuda { font: var(--mat-sys-body-small); text-align: center; margin-top: 16px; }
  `,
})
export class LoginPage {
  /** ?motivo=expirada cuando el token venció. */
  readonly motivo = input<string>();

  private readonly auth = inject(AuthService);
  private readonly router = inject(Router);

  readonly cargando = signal(false);
  readonly error = signal('');
  readonly verPassword = signal(false);

  readonly form = new FormGroup({
    email: new FormControl('', { nonNullable: true, validators: [Validators.required, Validators.email] }),
    password: new FormControl('', { nonNullable: true, validators: [Validators.required] }),
  });

  ingresar(): void {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }
    this.cargando.set(true);
    this.error.set('');
    const { email, password } = this.form.getRawValue();

    this.auth.login(email, password).subscribe({
      next: () => this.router.navigate(['/inicio']),
      error: (err) => {
        this.cargando.set(false);
        this.error.set(mensajeDeError(err, 'No se pudo iniciar sesión.'));
        this.form.controls.password.reset();
      },
    });
  }
}
