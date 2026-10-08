/**
 * Envío de comprobantes por WhatsApp.
 *
 * En el celular se usa el menú "Compartir" del sistema, que adjunta el PDF y
 * deja elegir WhatsApp. En la computadora no se pueden adjuntar archivos
 * desde una página: se abre WhatsApp Web con el chat del cliente y el resumen
 * ya escrito, y el PDF se descarga para adjuntarlo a mano.
 */

/**
 * Número en el formato internacional que pide WhatsApp para Argentina:
 * 54 + 9 + código de área (sin 0) + número (sin 15). Ej.: "0351 15-123-4567"
 * → "5493511234567". Devuelve null si no parece un número válido.
 */
export function telefonoWhatsApp(telefono: string | null | undefined): string | null {
  let d = (telefono ?? '').replace(/\D/g, '');
  if (!d) return null;
  if (d.startsWith('00')) d = d.slice(2);
  if (d.startsWith('549') && d.length === 13) return d;
  if (d.startsWith('54') && d.length === 12) return '549' + d.slice(2);
  if (d.startsWith('0')) d = d.slice(1);
  // código de área de 2 a 4 dígitos seguido del 15 de los celulares: se saca el 15
  if (d.length === 12) {
    for (const area of [2, 3, 4]) {
      if (d.slice(area, area + 2) === '15') {
        d = d.slice(0, area) + d.slice(area + 2);
        break;
      }
    }
  }
  return d.length === 10 ? '549' + d : null;
}

/** Link a un chat de WhatsApp con un mensaje escrito (sin número: elige el contacto). */
export function linkWhatsApp(telefono: string | null, mensaje: string): string {
  const destino = telefonoWhatsApp(telefono);
  return `https://wa.me/${destino ?? ''}?text=${encodeURIComponent(mensaje)}`;
}

/** true si el navegador puede compartir archivos (celulares y algunas compus). */
export function puedeCompartirArchivo(archivo: File): boolean {
  return typeof navigator !== 'undefined' && !!navigator.canShare && navigator.canShare({ files: [archivo] });
}
