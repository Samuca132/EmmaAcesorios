import { Component, inject, signal } from '@angular/core';
import { MatButtonModule } from '@angular/material/button';
import { MatIconModule } from '@angular/material/icon';
import { Columna, DataTable } from '../../shared/data-table';
import { CrudPage } from '../../shared/crud-page';
import { CampoFormulario } from '../../shared/form-dialog';
import { PageHeader } from '../../shared/page-header';
import { Router } from '@angular/router';
import { Ciudad, Cliente } from '../../core/models';
import { camposPersona } from '../proveedores/proveedores-page';

@Component({
  selector: 'app-clientes-page',
  imports: [PageHeader, DataTable, MatButtonModule, MatIconModule],
  templateUrl: './clientes-page.html',
})
export class ClientesPage extends CrudPage<Cliente> {
  private readonly router = inject(Router);
  readonly recurso = 'clientes';
  readonly entidad = 'cliente';
  readonly columnas: Columna<Cliente>[] = [
    { clave: 'nombre', titulo: 'Nombre' },
    { clave: 'ciudad', titulo: 'Ciudad' },
    { clave: 'telefono', titulo: 'Teléfono' },
    { clave: 'compras', titulo: 'Compras', tipo: 'numero' },
    { clave: 'totalComprado', titulo: 'Total comprado', tipo: 'moneda' },
  ];
  private readonly ciudades = signal<Ciudad[]>([]);

  override ngOnInit(): void {
    super.ngOnInit();
    this.api.ciudades().subscribe((c) => this.ciudades.set(c));
  }

  verPerfil(c: Cliente): void {
    this.router.navigate(['/clientes', c.id]);
  }

  protected campos(): CampoFormulario[] {
    return camposPersona(this.ciudades());
  }

  protected valoresDe(c: Cliente) {
    return { nombre: c.nombre, ciudadId: c.ciudadId, telefono: c.telefono };
  }
}
