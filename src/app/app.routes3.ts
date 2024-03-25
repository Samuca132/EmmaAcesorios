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
    { path: '', pathMatch: 'full', redirectTo: 'Index' },
    { path: 'Index' ,component:IndexComponent},
    { path: 'Ventas' ,component:VentasComponent},
    { path: 'Canjes' ,component:CanjesComponent},
    { path: 'Compras' ,component:ComprasComponent},
    { path: 'Productos' ,component:ProductosComponent},
    { path: 'Clientes' ,component:ClientesComponent},
    { path: 'Insumos' ,component:InsumosComponent},
    { path: 'Editar/:tabla/:id' ,component:EditarComponent},
    { path: 'Añadir/:tabla' ,component:AddComponent},
    { path: 'login/:Status' ,component:LoginComponent},
];
