import { Component, signal } from '@angular/core';
import { MatButtonModule } from '@angular/material/button';
import { MatIconModule } from '@angular/material/icon';
import { Columna, DataTable } from '../../shared/data-table';
import { CrudPage } from '../../shared/crud-page';
import { CampoFormulario } from '../../shared/form-dialog';
import { PageHeader } from '../../shared/page-header';
import { Ciudad, Provincia } from '../../core/models';

@Component({
  selector: 'app-ciudades-page',
  imports: [PageHeader, DataTable, MatButtonModule, MatIconModule],
  templateUrl: './ciudades-page.html',
})
export class CiudadesPage extends CrudPage<Ciudad> {
  readonly recurso = 'ciudades';
  readonly entidad = 'ciudad';
  readonly columnas: Columna<Ciudad>[] = [
    { clave: 'nombre', titulo: 'Ciudad' },
    { clave: 'provincia', titulo: 'Provincia' },
    { clave: 'clientes', titulo: 'Clientes', tipo: 'numero' },
  ];
  private readonly provincias = signal<Provincia[]>([]);

  override ngOnInit(): void {
    super.ngOnInit();
    this.api.provincias().subscribe((p) => this.provincias.set(p));
  }

  protected campos(): CampoFormulario[] {
    return [
      { clave: 'nombre', etiqueta: 'Nombre', tipo: 'texto', requerido: true, maxLength: 50 },
      {
        clave: 'provincia', etiqueta: 'Provincia', tipo: 'select', requerido: true,
        opciones: this.provincias().map((p) => ({ valor: p.id, texto: p.nombre })),
      },
    ];
  }

  protected valoresDe(c: Ciudad) {
    return { nombre: c.nombre, provincia: c.provinciaId };
  }
}
