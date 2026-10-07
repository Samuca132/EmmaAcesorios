import { Ticket } from '../../core/models';

const moneda = new Intl.NumberFormat('es-AR', { style: 'currency', currency: 'ARS' });

/**
 * Genera el comprobante en PDF usando public/factura.png como fondo.
 * jsPDF se carga recién cuando se usa para no agrandar la carga inicial.
 */
export async function descargarTicketPdf(ticket: Ticket): Promise<void> {
  const [{ jsPDF }, fondo] = await Promise.all([import('jspdf'), cargarImagen('factura.png')]);

  const doc = new jsPDF();
  const ancho = doc.internal.pageSize.getWidth();
  const alto = doc.internal.pageSize.getHeight();
  const pintarFondo = () => fondo && doc.addImage(fondo, 'PNG', 0, 0, ancho, alto);

  pintarFondo();
  doc.setFontSize(18);
  doc.text('Comprobante de venta', 30, 35);
  doc.setFontSize(12);
  doc.text(`Ticket N° ${ticket.id}`, 30, 44);
  doc.text(`Fecha: ${new Date(ticket.fecha.replace(' ', 'T')).toLocaleString('es-AR')}`, 30, 51);
  doc.text(`Cliente: ${ticket.cliente}`, 30, 58);
  doc.text(`Atendido por: ${ticket.usuario ?? '—'}`, 30, 65);

  let y = 80;
  doc.setFontSize(10);
  doc.setFont('helvetica', 'bold');
  doc.text('Producto', 30, y);
  doc.text('Cant.', 120, y, { align: 'right' });
  doc.text('P. unit.', 150, y, { align: 'right' });
  doc.text('Subtotal', 180, y, { align: 'right' });
  doc.setFont('helvetica', 'normal');
  y += 8;

  for (const item of ticket.items ?? []) {
    doc.text(item.producto ?? `#${item.productoId}`, 30, y, { maxWidth: 80 });
    doc.text(String(item.cantidad), 120, y, { align: 'right' });
    doc.text(moneda.format(item.precioUnitario), 150, y, { align: 'right' });
    doc.text(moneda.format(item.total), 180, y, { align: 'right' });
    y += 8;
    if (y > alto - 30) {
      doc.addPage();
      pintarFondo();
      y = 30;
    }
  }

  doc.setFontSize(13);
  doc.setFont('helvetica', 'bold');
  doc.text(`Total: ${moneda.format(ticket.total)}`, 180, y + 8, { align: 'right' });

  doc.save(`Ticket_${ticket.id}.pdf`);
}

async function cargarImagen(url: string): Promise<string | null> {
  try {
    const blob = await (await fetch(url)).blob();
    return await new Promise((resolve) => {
      const reader = new FileReader();
      reader.onload = () => resolve(reader.result as string);
      reader.onerror = () => resolve(null);
      reader.readAsDataURL(blob);
    });
  } catch {
    return null;
  }
}
