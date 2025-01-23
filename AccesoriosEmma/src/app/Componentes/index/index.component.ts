import { Component } from '@angular/core';
import { Router} from '@angular/router';
import { NavbarComponent } from '../navbar/navbar.component';


@Component({
  selector: 'app-index',
  standalone: true,
  imports: [NavbarComponent],
  templateUrl: './index.component.html',
  styleUrl: './index.component.css'
})
export class IndexComponent {

  constructor(
    private ruteador:Router
  ) { }

  // Método para cerrar sesión y eliminar el token
  logout() {
    localStorage.removeItem('token');
    
    this.ruteador.navigateByUrl('')
    // Redirigir a la página de inicio de sesión u otra página relevante
    // Puedes utilizar el enrutador de Angular para redirigir
  }

}
