import { Component,Renderer2, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterOutlet,Router,NavigationEnd  } from '@angular/router';
import { ServicesService } from '../../Servicios/services.service';

@Component({
  selector: 'app-navbar',
  standalone: true,
  imports: [],
  templateUrl: './navbar.component.html',
  styleUrl: './navbar.component.css'
})
export class NavbarComponent {
  menuVisible = false;

  constructor(private tokenService: ServicesService) {}
  
  ngOnInit() {
  }

  toggleMenu() {
    this.menuVisible = !this.menuVisible;
  }
}
