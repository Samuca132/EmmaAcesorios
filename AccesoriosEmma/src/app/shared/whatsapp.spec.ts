import { linkWhatsApp, telefonoWhatsApp } from './whatsapp';

describe('telefonoWhatsApp', () => {
  it('arma el formato 549 + área + número', () => {
    expect(telefonoWhatsApp('3510000000')).toBe('5493510000000');
    expect(telefonoWhatsApp('0351 15-123-4567')).toBe('5493511234567');
    expect(telefonoWhatsApp('011 15 2345-6789')).toBe('5491123456789');
    expect(telefonoWhatsApp('(02966) 15 12-3456')).toBe('5492966123456');
  });

  it('respeta números que ya vienen en formato internacional', () => {
    expect(telefonoWhatsApp('+54 9 351 123-4567')).toBe('5493511234567');
    expect(telefonoWhatsApp('+54 351 123-4567')).toBe('5493511234567');
    expect(telefonoWhatsApp('0054 9 351 1234567')).toBe('5493511234567');
  });

  it('devuelve null si no es un número utilizable', () => {
    expect(telefonoWhatsApp('')).toBeNull();
    expect(telefonoWhatsApp(null)).toBeNull();
    expect(telefonoWhatsApp('1234')).toBeNull();
  });
});

describe('linkWhatsApp', () => {
  it('codifica el mensaje y deja elegir el contacto si no hay número', () => {
    expect(linkWhatsApp('3510000000', 'Hola & chau')).toBe('https://wa.me/5493510000000?text=Hola%20%26%20chau');
    expect(linkWhatsApp(null, 'Hola')).toBe('https://wa.me/?text=Hola');
  });
});
