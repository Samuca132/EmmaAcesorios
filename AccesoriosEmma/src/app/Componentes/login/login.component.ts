// Importa las clases necesarias
import { Component } from '@angular/core';
import { FormBuilder, FormGroup } from '@angular/forms';
import { ServicesService } from '../../Servicios/services.service';
import { Router} from '@angular/router';
import { ReactiveFormsModule } from '@angular/forms'; // Importa ReactiveFormsModule

@Component({
  selector: 'app-login',
  templateUrl: './login.component.html',
  styleUrls: ['./login.component.css'],
  imports:[ReactiveFormsModule,],
  standalone: true
})
export class LoginComponent {

  formularioLogin: FormGroup;
  usuario:any;

  constructor(
    private formulario: FormBuilder,
    private service: ServicesService,
    private ruteador:Router
    
  ) {
    // Crea el formulario reactivo
    this.formularioLogin = this.formulario.group({
      email: [''],
      password: ['']
    });
  }

  // Método para manejar el envío del formulario
  onSubmit() {
    this.login();
  }

  // Método para enviar la solicitud de inicio de sesión al servicio
  login(): any {
    
    console.log(this.formularioLogin.value);
    this.usuario=this.formularioLogin;
    console.log(this.usuario.value);
    
    this.service.LoginPage(this.usuario.value).subscribe(response => {
    console.log(response.message);
      alert(response.message);

      if (response.message === "Inicio de sesión exitoso") {
        localStorage.setItem('token', response.token);
        console.log('token', response.token);
        this.ruteador.navigateByUrl('/Index')
      }

    });

  }

}
