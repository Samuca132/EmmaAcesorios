import { Component, inject } from '@angular/core';
import { MatButtonModule } from '@angular/material/button';
import { MAT_DIALOG_DATA, MatDialogModule } from '@angular/material/dialog';
import { Historial } from './historial';

export interface HistorialDialogData {
  entidad: string;
  entidadId: number;
  nombre: string;
}

/** Historial de cambios de un solo registro (abierto desde su catálogo). */
@Component({
  selector: 'app-historial-dialog',
  imports: [MatDialogModule, MatButtonModule, Historial],
  template: `
    <h2 mat-dialog-title>Historial de "{{ data.nombre }}"</h2>
    <mat-dialog-content>
      <app-historial [entidadFija]="data.entidad" [entidadIdFijo]="data.entidadId" />
    </mat-dialog-content>
    <mat-dialog-actions align="end">
      <button mat-button mat-dialog-close>Cerrar</button>
    </mat-dialog-actions>
  `,
})
export class HistorialDialog {
  readonly data = inject<HistorialDialogData>(MAT_DIALOG_DATA);
}
