import { Component,Renderer2, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterOutlet,Router,NavigationEnd  } from '@angular/router';
import { LoginComponent } from './Componentes/login/login.component';
import { NavbarComponent } from './Componentes/navbar/navbar.component';

@Component({
  selector: 'app-root',
  standalone: true,
  imports: [CommonModule, RouterOutlet,LoginComponent,NavbarComponent],
  templateUrl: './app.component.html',
  styleUrl: './app.component.css'
})

export class AppComponent implements OnInit {
  title = 'AccesoriosEmma';
  menuVisible = false;
  logged = false; // Inicialmente se establece en false

  constructor(private router: Router, private renderer: Renderer2) {}

  ngOnInit() {
    const token = localStorage.getItem('token');

    if (token) {
      // Si hay un token, se cambia el estado de logged a true
      this.logged = true;
      // Se establece el color de fondo del documento a blanco
      this.renderer.setStyle(document.body, 'background-color', '#FFFFFF');
      
    }


  }

}


