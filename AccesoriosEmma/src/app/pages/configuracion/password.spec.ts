import { FormControl, FormGroup } from '@angular/forms';
import { generarPassword, LARGO_MINIMO_PASSWORD, passwordsCoinciden } from './password';

describe('generarPassword', () => {
  it('genera contraseñas del largo pedido, válidas para el backend', () => {
    expect(generarPassword()).toHaveLength(20);
    expect(generarPassword(32)).toHaveLength(32);
    expect(generarPassword().length).toBeGreaterThanOrEqual(LARGO_MINIMO_PASSWORD);
  });

  it('no repite contraseñas ni usa caracteres que se confunden (0, O, 1, l, I)', () => {
    const generadas = Array.from({ length: 50 }, () => generarPassword());
    expect(new Set(generadas).size).toBe(50);
    expect(generadas.join('')).not.toMatch(/[0O1lI]/);
  });
});

describe('passwordsCoinciden', () => {
  const grupo = (password: string, repetir: string) =>
    new FormGroup({ password: new FormControl(password), repetir: new FormControl(repetir) }, { validators: passwordsCoinciden });

  it('marca el error solo cuando las contraseñas son distintas', () => {
    expect(grupo('una-clave-larga', 'una-clave-larga').valid).toBe(true);
    expect(grupo('una-clave-larga', 'otra-clave').hasError('noCoinciden')).toBe(true);
  });
});
