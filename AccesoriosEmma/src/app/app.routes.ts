import { Routes } from '@angular/router';
import { adminGuard, authGuard, invitadoGuard } from './core/auth.guard';
import { Shell } from './layout/shell';

export const routes: Routes = [
  {
    path: 'login',
    canActivate: [invitadoGuard],
    title: 'Ingresar · Emma Accesorios',
    loadComponent: () => import('./pages/login/login-page').then((m) => m.LoginPage),
  },
  {
    path: '',
    component: Shell,
    canActivate: [authGuard],
    children: [
      { path: '', pathMatch: 'full', redirectTo: 'inicio' },
      { path: 'inicio', title: 'Inicio · Emma', loadComponent: () => import('./pages/inicio/inicio-page').then((m) => m.InicioPage) },
      { path: 'ventas', title: 'Ventas · Emma', loadComponent: () => import('./pages/ventas/ventas-page').then((m) => m.VentasPage) },
      { path: 'compras', title: 'Compras · Emma', loadComponent: () => import('./pages/compras/compras-page').then((m) => m.ComprasPage) },
      { path: 'canjes', title: 'Canjes · Emma', loadComponent: () => import('./pages/canjes/canjes-page').then((m) => m.CanjesPage) },
      { path: 'reportes', title: 'Reportes · Emma', loadComponent: () => import('./pages/reportes/reportes-page').then((m) => m.ReportesPage) },
      { path: 'productos', title: 'Productos · Emma', loadComponent: () => import('./pages/productos/productos-page').then((m) => m.ProductosPage) },
      { path: 'insumos', title: 'Insumos · Emma', loadComponent: () => import('./pages/insumos/insumos-page').then((m) => m.InsumosPage) },
      { path: 'clientes', title: 'Clientes · Emma', loadComponent: () => import('./pages/clientes/clientes-page').then((m) => m.ClientesPage) },
      {
        path: 'clientes/:id',
        title: 'Cliente · Emma',
        loadComponent: () => import('./pages/cliente-detalle/cliente-detalle-page').then((m) => m.ClienteDetallePage),
      },
      { path: 'proveedores', title: 'Proveedores · Emma', loadComponent: () => import('./pages/proveedores/proveedores-page').then((m) => m.ProveedoresPage) },
      {
        path: 'configuracion',
        title: 'Configuración · Emma',
        canActivate: [adminGuard],
        loadComponent: () => import('./pages/configuracion/configuracion-page').then((m) => m.ConfiguracionPage),
      },
      { path: 'ciudades', title: 'Ciudades · Emma', loadComponent: () => import('./pages/ciudades/ciudades-page').then((m) => m.CiudadesPage) },
    ],
  },
  { path: '**', redirectTo: '' },
];
