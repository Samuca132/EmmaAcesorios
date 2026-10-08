import { TestBed } from '@angular/core/testing';
import { TemaService } from './tema.service';

describe('TemaService', () => {
  let sistemaOscuro = false;

  beforeEach(() => {
    localStorage.clear();
    document.documentElement.style.colorScheme = '';
    sistemaOscuro = false;
    // jsdom no implementa matchMedia: simulamos la preferencia del sistema operativo
    vi.stubGlobal('matchMedia', (query: string) => ({ matches: query.includes('dark') && sistemaOscuro }));
  });

  afterEach(() => vi.unstubAllGlobals());

  const crear = () => {
    const tema = TestBed.inject(TemaService);
    TestBed.tick(); // ejecuta el effect que aplica color-scheme
    return tema;
  };

  it('sin preferencia guardada sigue al sistema operativo', () => {
    sistemaOscuro = true;
    const tema = crear();
    expect(tema.oscuro()).toBe(true);
    expect(document.documentElement.style.colorScheme).toBe('dark');
  });

  it('la preferencia guardada gana sobre la del sistema', () => {
    sistemaOscuro = true;
    localStorage.setItem('emma.tema', 'claro');
    expect(crear().oscuro()).toBe(false);
    expect(document.documentElement.style.colorScheme).toBe('light');
  });

  it('alternar cambia el tema, lo aplica al instante y lo recuerda', () => {
    const tema = crear();

    tema.alternar();

    expect(tema.oscuro()).toBe(true);
    expect(document.documentElement.style.colorScheme).toBe('dark');
    expect(localStorage.getItem('emma.tema')).toBe('oscuro');
  });

  it('usa la transición del navegador cuando está disponible', () => {
    const tema = crear();
    const transicion = vi.fn((cambiar: () => void) => cambiar());
    Object.assign(document, { startViewTransition: transicion });

    tema.alternar();

    expect(transicion).toHaveBeenCalledOnce();
    expect(localStorage.getItem('emma.tema')).toBe('oscuro');
    delete (document as Partial<Document>).startViewTransition;
  });
});
