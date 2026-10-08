import { MatDialog } from '@angular/material/dialog';
import { ApiService } from '../../core/api.service';
import { Ticket } from '../../core/models';
import { pedirAnulacion } from '../../shared/anular';

/** Pide el motivo y anula la venta (desde la lista o desde el detalle del ticket). */
export function anularVenta(dialog: MatDialog, api: ApiService, t: Pick<Ticket, 'id' | 'cliente'>) {
  return pedirAnulacion(dialog, {
    titulo: `Anular venta N° ${t.id}`,
    mensaje: `La venta a ${t.cliente} queda en el historial marcada como anulada y el stock de sus productos vuelve. No se puede deshacer.`,
    anular: (motivo) => api.anular<Ticket>('ventas', t.id, motivo),
  });
}
