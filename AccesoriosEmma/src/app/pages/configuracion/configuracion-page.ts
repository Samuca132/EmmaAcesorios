import { Component, OnInit, inject, signal } from '@angular/core';
import { toSignal } from '@angular/core/rxjs-interop';
import { FormControl, FormGroup, FormGroupDirective, ReactiveFormsModule, Validators } from '@angular/forms';
import { MatDialog } from '@angular/material/dialog';
import { filter, switchMap } from 'rxjs';
import { MatButtonModule } from '@angular/material/button';
import { MatCardModule } from '@angular/material/card';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatIconModule } from '@angular/material/icon';
import { MatInputModule } from '@angular/material/input';
import { MatProgressSpinnerModule } from '@angular/material/progress-spinner';
import { MatSelectModule } from '@angular/material/select';
import { MatTabsModule } from '@angular/material/tabs';
import { MatTooltipModule } from '@angular/material/tooltip';
import { ApiService, sinError } from '../../core/api.service';
import { Rol, UsuarioAdmin } from '../../core/models';
import { NotificacionService, erroresDeCampos, mensajeDeError } from '../../core/notificacion.service';
import { AuthService } from '../../core/auth.service';
import { confirmar } from '../../shared/confirm-dialog';
import { AccionFila, Columna, DataTable } from '../../shared/data-table';
import { FormDialog, FormDialogData } from '../../shared/form-dialog';
import { LARGO_MINIMO_PASSWORD, generarPassword, passwordsCoinciden } from './password';
import { RestablecerPasswordDialog } from './restablecer-password-dialog';
import { PageHeader } from '../../shared/page-header';

const LARGO_MINIMO = LARGO_MINIMO_PASSWORD;

type FilaUsuario = UsuarioAdmin & { nombreVisible: string; estado: string; esYo: boolean };

/**
 * Configuración (solo administradores, rol 1). Pestañas: lista de usuarios y
 * alta de usuarios con el rol que corresponda.
 */
@Component({
  selector: 'app-configuracion-page',
  imports: [
    PageHeader, DataTable, ReactiveFormsModule, MatTabsModule, MatFormFieldModule, MatInputModule, MatSelectModule,
    MatButtonModule, MatIconModule, MatCardModule, MatTooltipModule, MatProgressSpinnerModule,
  ],
  template: `
    <div class="page">
      <app-page-header titulo="Configuración" subtitulo="Administración del sistema. Solo visible para administradores." />

      <mat-tab-group [(selectedIndex)]="pestania" mat-stretch-tabs="false" animationDuration="150ms">
        <mat-tab>
          <ng-template mat-tab-label><mat-icon class="tab-icono">group</mat-icon>Usuarios</ng-template>
          <div class="contenido">
            <app-data-table [columnas]="columnas" [datos]="usuarios()" [cargando]="cargando()"
                            [acciones]="acciones" [borrable]="noSoyYo" textoVacio="No hay usuarios."
                            (editar)="editar($event)" (borrar)="borrar($event)" (accion)="ejecutar($event)">
              <button mat-stroked-button (click)="pestania = 1"><mat-icon>person_add</mat-icon>Agregar usuario</button>
            </app-data-table>
          </div>
        </mat-tab>

        <mat-tab>
          <ng-template mat-tab-label><mat-icon class="tab-icono">person_add</mat-icon>Agregar usuario</ng-template>
          <div class="contenido">
            <mat-card appearance="outlined" class="tarjeta">
              <mat-card-header>
                <mat-card-title>Nuevo usuario</mat-card-title>
                <mat-card-subtitle>
                  Podrá ingresar con este email y contraseña. Los administradores además pueden entrar a Configuración.
                </mat-card-subtitle>
              </mat-card-header>
              <mat-card-content>
                <form [formGroup]="form" (ngSubmit)="crear(formDir)" #formDir="ngForm">
                  <div class="form-grid">
                    <mat-form-field>
                      <mat-label>Nombre</mat-label>
                      <input matInput formControlName="nombre" autocomplete="off" maxlength="50" />
                      <mat-error>{{ error('nombre') }}</mat-error>
                    </mat-form-field>
                    <mat-form-field>
                      <mat-label>Email</mat-label>
                      <input matInput type="email" formControlName="email" autocomplete="off" />
                      <mat-error>{{ error('email') }}</mat-error>
                    </mat-form-field>
                    <mat-form-field>
                      <mat-label>Rol</mat-label>
                      <mat-select formControlName="rol">
                        @for (r of roles(); track r.id) {
                          <mat-option [value]="r.id">{{ r.nombre }}</mat-option>
                        }
                      </mat-select>
                      <mat-error>{{ error('rol') }}</mat-error>
                    </mat-form-field>
                  </div>

                  <div class="form-grid">
                    <mat-form-field>
                      <mat-label>Contraseña</mat-label>
                      <input matInput [type]="verPassword() ? 'text' : 'password'" formControlName="password" autocomplete="new-password" />
                      <button mat-icon-button matSuffix type="button" (click)="verPassword.set(!verPassword())"
                              [attr.aria-label]="verPassword() ? 'Ocultar contraseña' : 'Mostrar contraseña'">
                        <mat-icon>{{ verPassword() ? 'visibility_off' : 'visibility' }}</mat-icon>
                      </button>
                      <mat-hint>Mínimo {{ largoMinimo }} caracteres.</mat-hint>
                      <mat-error>{{ error('password') }}</mat-error>
                    </mat-form-field>
                    <mat-form-field>
                      <mat-label>Repetir contraseña</mat-label>
                      <input matInput [type]="verPassword() ? 'text' : 'password'" formControlName="repetir" autocomplete="new-password" />
                      @if (form.hasError('noCoinciden') && form.controls.repetir.touched) {
                        <mat-hint class="hint-error">Las contraseñas no coinciden.</mat-hint>
                      }
                    </mat-form-field>
                  </div>

                  <button mat-button type="button" (click)="generar()">
                    <mat-icon>key</mat-icon>Generar contraseña segura
                  </button>
                  @if (generada()) {
                    <p class="aviso" role="status">
                      <mat-icon>info</mat-icon>
                      Se generó una contraseña de 20 caracteres y quedó visible en el campo. Copiala y pasásela al
                      usuario de forma segura: después no se vuelve a mostrar.
                    </p>
                  }
                  @if (errorGeneral()) {
                    <p class="error" role="alert">{{ errorGeneral() }}</p>
                  }

                  <div class="acciones">
                    <button mat-button type="button" (click)="limpiar(formDir)" [disabled]="guardando()">Limpiar</button>
                    <button mat-flat-button type="submit" [disabled]="guardando()">
                      @if (guardando()) { <mat-spinner diameter="18" /> } @else { <mat-icon>person_add</mat-icon> }
                      Crear usuario
                    </button>
                  </div>
                </form>
              </mat-card-content>
            </mat-card>
          </div>
        </mat-tab>
      </mat-tab-group>
    </div>
  `,
  styles: `
    .tab-icono { margin-right: 8px; }
    .contenido { padding-top: 20px; }
    .tarjeta { max-width: 860px; }
    form { margin-top: 16px; }
    .acciones { display: flex; justify-content: flex-end; gap: 8px; margin-top: 8px; }
    .aviso { display: flex; gap: 8px; align-items: flex-start; padding: 12px; border-radius: 12px;
      background: var(--mat-sys-tertiary-container); color: var(--mat-sys-on-tertiary-container); }
    .error, .hint-error { color: var(--mat-sys-error); }
    mat-spinner { display: inline-block; margin-right: 8px; }
  `,
})
export class ConfiguracionPage implements OnInit {
  private readonly api = inject(ApiService);
  private readonly auth = inject(AuthService);
  private readonly dialog = inject(MatDialog);
  private readonly notificacion = inject(NotificacionService);

  readonly largoMinimo = LARGO_MINIMO;
  pestania = 0;

  readonly roles = toSignal(sinError(this.api.adminRoles()), { initialValue: [] as Rol[] });
  readonly usuarios = signal<FilaUsuario[]>([]);
  readonly cargando = signal(true);
  readonly guardando = signal(false);
  readonly verPassword = signal(false);
  readonly generada = signal(false);
  readonly errorGeneral = signal('');

  readonly columnas: Columna<FilaUsuario>[] = [
    { clave: 'nombreVisible', titulo: 'Nombre' },
    { clave: 'email', titulo: 'Email' },
    { clave: 'rolNombre', titulo: 'Rol' },
    { clave: 'estado', titulo: 'Estado' },
    { clave: 'ultimoLogin', titulo: 'Último ingreso', tipo: 'fechaHora' },
    { clave: 'creado', titulo: 'Creado', tipo: 'fechaHora' },
  ];

  /** Acciones extra del menú ⋮ (Editar y Borrar ya los da la tabla). */
  readonly acciones: AccionFila<FilaUsuario>[] = [
    { id: 'password', texto: 'Restablecer contraseña', icono: 'key' },
    {
      id: 'estado',
      texto: (u) => (u.activo ? 'Desactivar' : 'Activar'),
      icono: (u) => (u.activo ? 'block' : 'check_circle'),
      visible: (u) => !u.esYo,
    },
  ];
  /** Nadie puede borrarse a sí mismo (el backend también lo impide). */
  readonly noSoyYo = (u: FilaUsuario) => !u.esYo;

  readonly form = new FormGroup(
    {
      nombre: new FormControl('', { nonNullable: true, validators: [Validators.required, Validators.maxLength(50)] }),
      email: new FormControl('', { nonNullable: true, validators: [Validators.required, Validators.email] }),
      rol: new FormControl<number>(2, { nonNullable: true, validators: Validators.required }),
      password: new FormControl('', {
        nonNullable: true,
        validators: [Validators.required, Validators.minLength(LARGO_MINIMO)],
      }),
      repetir: new FormControl('', { nonNullable: true, validators: Validators.required }),
    },
    { validators: passwordsCoinciden },
  );

  ngOnInit(): void {
    this.cargar();
  }

  cargar(): void {
    this.cargando.set(true);
    this.api.adminUsuarios().subscribe({
      next: (usuarios) => {
        const miId = this.auth.usuario()?.id;
        this.usuarios.set(usuarios.map((u) => ({
          ...u,
          esYo: u.id === miId,
          nombreVisible: u.id === miId ? `${u.nombre} (vos)` : u.nombre,
          estado: !u.activo ? 'Inactivo' : u.bloqueado ? 'Bloqueado temporalmente' : 'Activo',
        })));
        this.cargando.set(false);
      },
      error: (err) => {
        this.cargando.set(false);
        this.notificacion.error(err);
      },
    });
  }

  editar(u: FilaUsuario): void {
    this.dialog
      .open<FormDialog<UsuarioAdmin>, FormDialogData<UsuarioAdmin>, UsuarioAdmin>(FormDialog, {
        data: {
          titulo: `Editar usuario`,
          campos: [
            { clave: 'nombre', etiqueta: 'Nombre', tipo: 'texto', requerido: true, maxLength: 50, ancho: true },
            { clave: 'email', etiqueta: 'Email', tipo: 'texto', requerido: true, maxLength: 100, ancho: true },
            {
              clave: 'rol', etiqueta: 'Rol', tipo: 'select', requerido: true, ancho: true,
              opciones: this.roles().map((r) => ({ valor: r.id, texto: r.nombre })),
              ayuda: u.esYo ? 'No podés quitarte el rol de administrador a vos mismo.' : '',
            },
          ],
          valores: { nombre: u.nombre, email: u.email, rol: u.rol },
          guardar: (v) => this.api.editarUsuario(u.id, v as { nombre: string; email: string; rol: number }),
        },
      })
      .afterClosed()
      .pipe(filter((r): r is UsuarioAdmin => !!r))
      .subscribe((actualizado) => {
        if (u.esYo) {
          this.auth.actualizarDatos({ nombre: actualizado.nombre, email: actualizado.email });
        }
        this.notificacion.ok('Usuario actualizado.');
        this.cargar();
      });
  }

  ejecutar({ id, fila }: { id: string; fila: FilaUsuario }): void {
    if (id === 'password') {
      this.dialog
        .open(RestablecerPasswordDialog, { data: fila, width: '480px' })
        .afterClosed()
        .pipe(filter(Boolean))
        .subscribe(() => {
          this.notificacion.ok(`Se cambió la contraseña de ${fila.nombre}.`);
          this.cargar();
        });
    } else if (id === 'estado') {
      const activar = !fila.activo;
      confirmar(this.dialog, {
        titulo: activar ? 'Activar usuario' : 'Desactivar usuario',
        mensaje: activar
          ? `${fila.nombre} va a poder volver a ingresar al sistema.`
          : `${fila.nombre} no va a poder ingresar y, si tiene una sesión abierta, se le cierra. Podés volver a activarlo cuando quieras.`,
        confirmar: activar ? 'Activar' : 'Desactivar',
      })
        .pipe(
          filter(Boolean),
          switchMap(() => this.api.cambiarEstadoUsuario(fila.id, activar)),
        )
        .subscribe({
          next: () => {
            this.notificacion.ok(activar ? `Se activó a ${fila.nombre}.` : `Se desactivó a ${fila.nombre}.`);
            this.cargar();
          },
          error: (err) => this.notificacion.error(err),
        });
    }
  }

  borrar(u: FilaUsuario): void {
    confirmar(this.dialog, {
      titulo: 'Borrar usuario',
      mensaje: `¿Seguro que querés borrar a ${u.nombre}? No va a poder ingresar más. Las ventas, compras y canjes que registró se conservan.`,
      confirmar: 'Borrar',
    })
      .pipe(
        filter(Boolean),
        switchMap(() => this.api.borrarUsuario(u.id)),
      )
      .subscribe({
        next: () => {
          this.notificacion.ok(`Se borró a ${u.nombre}.`);
          this.cargar();
        },
        error: (err) => this.notificacion.error(err),
      });
  }

  error(campo: 'nombre' | 'email' | 'rol' | 'password'): string {
    const c = this.form.controls[campo];
    if (c.hasError('required')) return 'Este campo es obligatorio.';
    if (c.hasError('email')) return 'El email no es válido.';
    if (c.hasError('minlength')) return `Mínimo ${LARGO_MINIMO} caracteres.`;
    if (c.hasError('maxlength')) return 'Demasiado largo.';
    return c.getError('servidor') ?? '';
  }

  generar(): void {
    const password = generarPassword();
    this.form.patchValue({ password, repetir: password });
    this.verPassword.set(true);
    this.generada.set(true);
  }

  limpiar(formDir: FormGroupDirective): void {
    formDir.resetForm({ nombre: '', email: '', rol: 2, password: '', repetir: '' });
    this.generada.set(false);
    this.verPassword.set(false);
    this.errorGeneral.set('');
  }

  crear(formDir: FormGroupDirective): void {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }
    this.guardando.set(true);
    this.errorGeneral.set('');
    const { nombre, email, rol, password } = this.form.getRawValue();

    this.api.crearUsuario({ nombre: nombre.trim(), email: email.trim(), rol, password }).subscribe({
      next: (usuario) => {
        this.guardando.set(false);
        this.notificacion.ok(`Se creó el usuario ${usuario.nombre} (${usuario.rolNombre}).`);
        this.limpiar(formDir);
        this.cargar();
        this.pestania = 0;
      },
      error: (err) => {
        this.guardando.set(false);
        const campos = erroresDeCampos(err);
        let asignado = false;
        for (const [campo, mensaje] of Object.entries(campos)) {
          const control = this.form.get(campo);
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
}
