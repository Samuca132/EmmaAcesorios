import { Component, inject } from '@angular/core';
import { RouterOutlet } from '@angular/router';
import { ConexionService } from './core/conexion.service';
import { TemaService } from './core/tema.service';

@Component({
  selector: 'app-root',
  imports: [RouterOutlet],
  template: '<router-outlet />',
})
export class App {
  // Se crea al arrancar para aplicar el tema guardado también en el login
  private readonly tema = inject(TemaService);
  // Aviso de versión nueva (app instalable)
  private readonly conexion = inject(ConexionService);
}
