import { MatDialog } from '@angular/material/dialog';
import { ApiService } from '../../core/api.service';
import { PaseVenta } from '../../core/models';
import { pedirAnulacion } from '../../shared/anular';

export function anularPase(dialog: MatDialog, api: ApiService, p: Pick<PaseVenta, 'id' | 'productos'>) {
  return pedirAnulacion(dialog, {
    titulo: `Anular pase a venta N° ${p.id}`,
    mensaje: `Salen del stock de venta ${p.productos} y los insumos vuelven a su stock. Si esos productos ya se vendieron, no se puede anular.`,
    anular: (motivo) => api.anular<PaseVenta>('pases-venta', p.id, motivo),
  });
}
