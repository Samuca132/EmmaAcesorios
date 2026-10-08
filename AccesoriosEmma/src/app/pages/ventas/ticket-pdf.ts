import { Ticket } from '../../core/models';

const moneda = new Intl.NumberFormat('es-AR', { style: 'currency', currency: 'ARS' });

/** Descarga el comprobante en PDF. */
export async function descargarTicketPdf(ticket: Ticket): Promise<void> {
  const { doc, nombre } = await armarTicketPdf(ticket);
  doc.save(nombre);
}

/** El comprobante como archivo, para compartirlo (WhatsApp, etc.). */
export async function ticketPdfComoArchivo(ticket: Ticket): Promise<File> {
  const { doc, nombre } = await armarTicketPdf(ticket);
  return new File([doc.output('blob')], nombre, { type: 'application/pdf' });
}

/**
 * Genera el comprobante en PDF usando public/factura.png como fondo.
 * jsPDF se carga recién cuando se usa para no agrandar la carga inicial.
 */
async function armarTicketPdf(ticket: Ticket) {
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

  if (ticket.anulado) {
    marcarAnulado(doc, ancho, alto, ticket.motivoAnulacion);
  }

  return { doc, nombre: ticket.anulado ? `Ticket_${ticket.id}_ANULADO.pdf` : `Ticket_${ticket.id}.pdf` };
}

/** Sello "ANULADO" en diagonal sobre cada página, con el motivo al pie. */
function marcarAnulado(doc: import('jspdf').jsPDF, ancho: number, alto: number, motivo: string | null): void {
  for (let pagina = 1; pagina <= doc.getNumberOfPages(); pagina++) {
    doc.setPage(pagina);
    doc.setTextColor(200, 30, 30);
    doc.setFont('helvetica', 'bold');
    doc.setFontSize(72);
    doc.text('ANULADO', ancho / 2, alto / 2, { align: 'center', angle: 35 });
    doc.setFontSize(10);
    doc.setFont('helvetica', 'normal');
    doc.text(`Venta anulada. Motivo: ${motivo ?? '—'}`, ancho / 2, alto - 15, { align: 'center', maxWidth: ancho - 40 });
  }
  doc.setTextColor(0, 0, 0);
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
