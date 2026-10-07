import { BreakpointObserver, Breakpoints } from '@angular/cdk/layout';
import { Component, inject } from '@angular/core';
import { toSignal } from '@angular/core/rxjs-interop';
import { MatButtonModule } from '@angular/material/button';
import { MatDialog } from '@angular/material/dialog';
import { MatDividerModule } from '@angular/material/divider';
import { MatIconModule } from '@angular/material/icon';
import { MatListModule } from '@angular/material/list';
import { MatMenuModule } from '@angular/material/menu';
import { MatSidenav, MatSidenavModule } from '@angular/material/sidenav';
import { MatToolbarModule } from '@angular/material/toolbar';
import { RouterLink, RouterLinkActive, RouterOutlet } from '@angular/router';
import { map } from 'rxjs';
import { AuthService } from '../core/auth.service';
import { NotificacionService } from '../core/notificacion.service';
import { CambiarPasswordDialog } from './cambiar-password-dialog';

interface ItemMenu {
  ruta: string;
  texto: string;
  icono: string;
}

/** Estructura principal: barra superior + menú lateral (navigation drawer). */
@Component({
  selector: 'app-shell',
  imports: [
    RouterOutlet, RouterLink, RouterLinkActive, MatSidenavModule, MatToolbarModule, MatListModule,
    MatIconModule, MatButtonModule, MatMenuModule, MatDividerModule,
  ],
  template: `
    <mat-sidenav-container class="contenedor">
      <mat-sidenav #drawer [mode]="esMovil() ? 'over' : 'side'" [opened]="!esMovil()" class="drawer">
        <div class="marca">
          <img src="logoEmma.png" alt="" width="48" height="48" />
          <span>Emma Accesorios</span>
        </div>
        @for (grupo of menu; track grupo.titulo) {
          <mat-nav-list>
            <div mat-subheader>{{ grupo.titulo }}</div>
            @for (item of grupo.items; track item.ruta) {
              <a mat-list-item [routerLink]="item.ruta" routerLinkActive #rla="routerLinkActive"
                 [activated]="rla.isActive" (click)="cerrarEnMovil(drawer)">
                <mat-icon matListItemIcon>{{ item.icono }}</mat-icon>
                <span matListItemTitle>{{ item.texto }}</span>
              </a>
            }
          </mat-nav-list>
        }
      </mat-sidenav>

      <mat-sidenav-content>
        <mat-toolbar class="barra">
          @if (esMovil()) {
            <button mat-icon-button aria-label="Abrir menú" (click)="drawer.toggle()"><mat-icon>menu</mat-icon></button>
          }
          <span class="spacer"></span>
          <button mat-button [matMenuTriggerFor]="menuUsuario">
            <mat-icon>account_circle</mat-icon>{{ auth.usuario()?.nombre }}
          </button>
          <mat-menu #menuUsuario="matMenu" xPosition="before">
            <div class="usuario-email text-muted">{{ auth.usuario()?.email }}</div>
            <mat-divider />
            <button mat-menu-item (click)="cambiarPassword()"><mat-icon>key</mat-icon>Cambiar contraseña</button>
            <button mat-menu-item (click)="auth.logout()"><mat-icon>logout</mat-icon>Cerrar sesión</button>
          </mat-menu>
        </mat-toolbar>
        <router-outlet />
      </mat-sidenav-content>
    </mat-sidenav-container>
  `,
  styles: `
    .contenedor { height: 100vh; }
    .drawer { width: 260px; border-right: 1px solid var(--mat-sys-outline-variant); }
    .marca { display: flex; align-items: center; gap: 12px; padding: 16px; font: var(--mat-sys-title-medium); }
    .barra {
      position: sticky; top: 0; z-index: 2;
      background: var(--mat-sys-surface-container-lowest);
      border-bottom: 1px solid var(--mat-sys-outline-variant);
    }
    .usuario-email { padding: 8px 16px; font: var(--mat-sys-body-small); }
  `,
})
export class Shell {
  readonly auth = inject(AuthService);
  private readonly dialog = inject(MatDialog);
  private readonly notificacion = inject(NotificacionService);

  readonly esMovil = toSignal(
    inject(BreakpointObserver).observe([Breakpoints.XSmall, Breakpoints.Small]).pipe(map((r) => r.matches)),
    { initialValue: false },
  );

  readonly menu: { titulo: string; items: ItemMenu[] }[] = [
    {
      titulo: 'General',
      items: [{ ruta: '/inicio', texto: 'Inicio', icono: 'dashboard' }],
    },
    {
      titulo: 'Operaciones',
      items: [
        { ruta: '/ventas', texto: 'Ventas', icono: 'point_of_sale' },
        { ruta: '/compras', texto: 'Compras', icono: 'shopping_cart' },
        { ruta: '/canjes', texto: 'Canjes', icono: 'swap_horiz' },
      ],
    },
    {
      titulo: 'Análisis',
      items: [{ ruta: '/reportes', texto: 'Reportes', icono: 'summarize' }],
    },
    {
      titulo: 'Catálogos',
      items: [
        { ruta: '/productos', texto: 'Productos', icono: 'inventory_2' },
        { ruta: '/insumos', texto: 'Insumos', icono: 'category' },
        { ruta: '/clientes', texto: 'Clientes', icono: 'groups' },
        { ruta: '/proveedores', texto: 'Proveedores', icono: 'local_shipping' },
        { ruta: '/ciudades', texto: 'Ciudades', icono: 'location_city' },
      ],
    },
  ];

  cerrarEnMovil(drawer: MatSidenav): void {
    if (this.esMovil()) drawer.close();
  }

  cambiarPassword(): void {
    this.dialog
      .open(CambiarPasswordDialog, { width: '420px' })
      .afterClosed()
      .subscribe((ok) => ok && this.notificacion.ok('Contraseña actualizada.'));
  }
}
