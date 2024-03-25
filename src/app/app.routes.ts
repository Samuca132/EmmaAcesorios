import { Routes } from '@angular/router';
import { AuthGuard } from './auth.guard';

import { IndexComponent } from './Componentes/index/index.component';
import { VentasComponent } from './Componentes/ventas/ventas.component';
import { CanjesComponent } from './Componentes/canjes/canjes.component';
import { ComprasComponent } from './Componentes/compras/compras.component';
import { ProductosComponent } from './Componentes/productos/productos.component';
import { ClientesComponent } from './Componentes/clientes/clientes.component';
import { InsumosComponent } from './Componentes/insumos/insumos.component';
import { EditarComponent } from './Componentes/editar/editar.component';
import { AddComponent } from './Componentes/add/add.component';
import { LoginComponent } from './Componentes/login/login.component';


export const routes: Routes = [
    { path: '', pathMatch: 'full', redirectTo: 'login' },
    { path: 'Index', component: IndexComponent,canActivate: [AuthGuard]  },
    { path: 'Ventas', component: VentasComponent, canActivate: [AuthGuard] },
    { path: 'Canjes', component: CanjesComponent, canActivate: [AuthGuard] },
    { path: 'Compras', component: ComprasComponent, canActivate: [AuthGuard] },
    { path: 'Productos', component: ProductosComponent, canActivate: [AuthGuard] },
    { path: 'Clientes', component: ClientesComponent, canActivate: [AuthGuard] },
    { path: 'Insumos', component: InsumosComponent, canActivate: [AuthGuard] },
    { path: 'Editar/:tabla/:id', component: EditarComponent, canActivate: [AuthGuard] },
    { path: 'Añadir/:tabla', component: AddComponent, canActivate: [AuthGuard] },
    { path: 'login', component: LoginComponent },
];
