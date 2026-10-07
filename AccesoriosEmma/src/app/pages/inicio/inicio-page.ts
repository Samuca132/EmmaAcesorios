import { CurrencyPipe } from '@angular/common';
import { Component, inject } from '@angular/core';
import { toSignal } from '@angular/core/rxjs-interop';
import { MatButtonModule } from '@angular/material/button';
import { MatCardModule } from '@angular/material/card';
import { MatIconModule } from '@angular/material/icon';
import { MatListModule } from '@angular/material/list';
import { MatProgressBarModule } from '@angular/material/progress-bar';
import { RouterLink } from '@angular/router';
import { ApiService } from '../../core/api.service';
import { AuthService } from '../../core/auth.service';
import { NotificacionService } from '../../core/notificacion.service';
import { catchError, of } from 'rxjs';

@Component({
  selector: 'app-inicio-page',
  imports: [MatCardModule, MatIconModule, MatButtonModule, MatListModule, MatProgressBarModule, RouterLink, CurrencyPipe],
  template: `
    <div class="page">
      <header class="page-header">
        <div>
          <h1>Hola, {{ auth.usuario()?.nombre }}</h1>
          <p class="text-muted sub">Resumen del mes en curso.</p>
        </div>
        <a mat-flat-button routerLink="/ventas"><mat-icon>point_of_sale</mat-icon>Ir a ventas</a>
      </header>

      @if (resumen(); as r) {
        <section class="kpis">
          <mat-card appearance="outlined">
            <mat-card-content>
              <mat-icon class="icono">payments</mat-icon>
              <span class="label">Ventas del mes</span>
              <span class="valor">{{ r.ventasMes | currency: 'ARS' : 'symbol-narrow' : '1.0-0' : 'es-AR' }}</span>
              <span class="text-muted">{{ r.ticketsMes }} tickets</span>
            </mat-card-content>
          </mat-card>
          <mat-card appearance="outlined">
            <mat-card-content>
              <mat-icon class="icono">trending_up</mat-icon>
              <span class="label">Ganancia del mes</span>
              <span class="valor">{{ r.gananciaMes | currency: 'ARS' : 'symbol-narrow' : '1.0-0' : 'es-AR' }}</span>
              <span class="text-muted">precio − coste de producción</span>
            </mat-card-content>
          </mat-card>
          <mat-card appearance="outlined">
            <mat-card-content>
              <mat-icon class="icono">local_shipping</mat-icon>
              <span class="label">Compras del mes</span>
              <span class="valor">{{ r.comprasMes | currency: 'ARS' : 'symbol-narrow' : '1.0-0' : 'es-AR' }}</span>
              <span class="text-muted">en insumos</span>
            </mat-card-content>
          </mat-card>
          <mat-card appearance="outlined">
            <mat-card-content>
              <mat-icon class="icono">groups</mat-icon>
              <span class="label">Clientes</span>
              <span class="valor">{{ r.clientes }}</span>
              <span class="text-muted">{{ r.productos }} productos en catálogo</span>
            </mat-card-content>
          </mat-card>
        </section>

        <mat-card appearance="outlined">
          <mat-card-header>
            <mat-icon mat-card-avatar class="alerta">warning</mat-icon>
            <mat-card-title>Stock bajo</mat-card-title>
            <mat-card-subtitle>Productos con 5 unidades o menos</mat-card-subtitle>
          </mat-card-header>
          <mat-card-content>
            @if (r.stockBajo.length) {
              <mat-list>
                @for (p of r.stockBajo; track p.id) {
                  <mat-list-item>
                    <span matListItemTitle>{{ p.nombre }}</span>
                    <span matListItemMeta [class.alerta]="p.stock === 0">{{ p.stock }} u.</span>
                  </mat-list-item>
                }
              </mat-list>
            } @else {
              <p class="text-muted">Todo en orden 🎉</p>
            }
          </mat-card-content>
          <mat-card-actions align="end">
            <a mat-button routerLink="/productos">Ver productos</a>
          </mat-card-actions>
        </mat-card>
      } @else {
        <mat-progress-bar mode="indeterminate" />
      }
    </div>
  `,
  styles: `
    .sub { margin: 4px 0 0; }
    .kpis { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin-bottom: 16px; }
    .kpis mat-card-content { display: flex; flex-direction: column; gap: 4px; padding-top: 16px; }
    .icono { color: var(--mat-sys-primary); }
    .label { font: var(--mat-sys-label-large); color: var(--mat-sys-on-surface-variant); }
    .valor { font: var(--mat-sys-headline-medium); }
    .alerta { color: var(--mat-sys-error); }
  `,
})
export class InicioPage {
  readonly auth = inject(AuthService);
  private readonly notificacion = inject(NotificacionService);
  readonly resumen = toSignal(
    inject(ApiService).dashboard().pipe(
      catchError((err) => {
        this.notificacion.error(err);
        return of(undefined);
      }),
    ),
  );
}
