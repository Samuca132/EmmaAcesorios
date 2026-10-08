import { HttpErrorResponse } from '@angular/common/http';
import { erroresDeCampos, mensajeDeError } from './notificacion.service';

const error = (status: number, cuerpo: unknown = null) => new HttpErrorResponse({ status, error: cuerpo });

describe('mensajeDeError', () => {
  it('muestra el mensaje que manda el backend', () => {
    expect(mensajeDeError(error(409, { message: 'No hay stock suficiente de "Collar".' }))).toBe(
      'No hay stock suficiente de "Collar".',
    );
  });

  it('avisa que no hay conexión cuando el backend no responde', () => {
    for (const status of [0, 502, 503, 504]) {
      expect(mensajeDeError(error(status))).toContain('No se pudo conectar');
    }
    expect(mensajeDeError(error(500, '<html>Proxy error</html>'))).toContain('No se pudo conectar');
  });

  it('detecta cuando apiUrl no apunta a la API (respuesta OK que no es JSON)', () => {
    expect(mensajeDeError(error(200))).toContain('no es la API');
  });

  it('usa el mensaje por defecto para cualquier otra cosa', () => {
    expect(mensajeDeError(new Error('x'))).toBe('Ocurrió un error. Intentá de nuevo.');
    expect(mensajeDeError(error(404), 'No encontrado')).toBe('No encontrado');
  });
});

describe('erroresDeCampos', () => {
  it('devuelve los errores por campo solo en un 422', () => {
    const errores = { email: 'Ya existe un usuario con ese email.' };
    expect(erroresDeCampos(error(422, { message: 'Datos inválidos.', errors: errores }))).toEqual(errores);
    expect(erroresDeCampos(error(409, { errors: errores }))).toEqual({});
    expect(erroresDeCampos(error(422, null))).toEqual({});
  });
});
