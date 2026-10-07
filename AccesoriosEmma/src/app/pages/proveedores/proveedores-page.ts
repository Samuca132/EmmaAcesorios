import { Component, signal } from '@angular/core';
import { MatButtonModule } from '@angular/material/button';
import { MatIconModule } from '@angular/material/icon';
import { Columna, DataTable } from '../../shared/data-table';
import { CrudPage } from '../../shared/crud-page';
import { CampoFormulario } from '../../shared/form-dialog';
import { PageHeader } from '../../shared/page-header';
import { Ciudad, Proveedor } from '../../core/models';

@Component({
  selector: 'app-proveedores-page',
  imports: [PageHeader, DataTable, MatButtonModule, MatIconModule],
  templateUrl: './proveedores-page.html',
})
export class ProveedoresPage extends CrudPage<Proveedor> {
  readonly recurso = 'proveedores';
  readonly entidad = 'proveedor';
  readonly columnas: Columna<Proveedor>[] = [
    { clave: 'nombre', titulo: 'Nombre' },
    { clave: 'ciudad', titulo: 'Ciudad' },
    { clave: 'telefono', titulo: 'Teléfono' },
  ];
  private readonly ciudades = signal<Ciudad[]>([]);

  override ngOnInit(): void {
    super.ngOnInit();
    this.api.ciudades().subscribe((c) => this.ciudades.set(c));
  }

  protected campos(): CampoFormulario[] {
    return camposPersona(this.ciudades());
  }

  protected valoresDe(p: Proveedor) {
    return { nombre: p.nombre, ciudadId: p.ciudadId, telefono: p.telefono };
  }
}

/** Campos compartidos por clientes y proveedores. */
export function camposPersona(ciudades: Ciudad[]): CampoFormulario[] {
  return [
    { clave: 'nombre', etiqueta: 'Nombre', tipo: 'texto', requerido: true, maxLength: 50, ancho: true },
    {
      clave: 'ciudadId', etiqueta: 'Ciudad', tipo: 'select',
      opciones: ciudades.map((c) => ({ valor: c.id, texto: `${c.nombre} (${c.provincia})` })),
      ayuda: ciudades.length ? '' : 'Primero cargá ciudades en la sección Ciudades',
    },
    { clave: 'telefono', etiqueta: 'Teléfono', tipo: 'telefono', maxLength: 20 },
  ];
}
