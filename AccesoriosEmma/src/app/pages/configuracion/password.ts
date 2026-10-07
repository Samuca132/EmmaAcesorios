import { AbstractControl, ValidationErrors } from '@angular/forms';

export const LARGO_MINIMO_PASSWORD = 12;

/** Contraseña aleatoria con el generador criptográfico del navegador. */
export function generarPassword(largo = 20): string {
  const caracteres = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#$%*-_=+?';
  const valores = crypto.getRandomValues(new Uint32Array(largo));
  return Array.from(valores, (v) => caracteres[v % caracteres.length]).join('');
}

/** Validador de grupo: los campos "password" y "repetir" deben coincidir. */
export const passwordsCoinciden = (g: AbstractControl): ValidationErrors | null =>
  g.get('password')?.value === g.get('repetir')?.value ? null : { noCoinciden: true };
