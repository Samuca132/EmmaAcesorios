export interface Usuario {
  id: number;
  nombre: string;
  email: string;
  rol: number;
  roles: string[];
}

export interface LoginResponse {
  token: string;
  expiraEn: number;
  usuario: Usuario;
}

export interface Producto {
  id: number;
  nombre: string;
  stock: number;
  precio: number;
  coste: number;
  ganancia: number;
}

export interface Insumo {
  id: number;
  nombre: string;
  stock: number;
  precio: number;
  descuentoCanje: number;
}

export interface Ciudad {
  id: number;
  nombre: string;
  provinciaId: number;
  provincia: string;
  clientes: number;
}

export interface Provincia {
  id: number;
  nombre: string;
}

export interface Cliente {
  id: number;
  nombre: string;
  ciudadId: number | null;
  ciudad: string | null;
  telefono: string;
  compras: number;
  totalComprado: number;
}

export interface Proveedor {
  id: number;
  nombre: string;
  ciudadId: number | null;
  ciudad: string | null;
  telefono: string;
}

export interface Compra {
  id: number;
  fecha: string;
  proveedorId: number;
  proveedor: string;
  insumoId: number;
  insumo: string;
  cantidad: number;
  costo: number;
  /** Usuario que registró la compra (null en registros anteriores). */
  usuario: string | null;
}

export interface Canje {
  id: number;
  fecha: string;
  proveedorId: number;
  proveedor: string;
  productoId: number;
  producto: string;
  insumoId: number;
  insumo: string;
  cantidadProducto: number;
  cantidadInsumo: number;
  profit: number;
  usuario: string | null;
}

export interface TicketItem {
  productoId: number;
  producto: string;
  cantidad: number;
  precioUnitario: number;
  total: number;
}

export interface Ticket {
  id: number;
  fecha: string;
  clienteId: number;
  cliente: string;
  ciudad: string | null;
  cantidadProductos: number;
  total: number;
  usuario: string | null;
  items?: TicketItem[];
}

export type TipoReporte = 'ventas' | 'compras' | 'canjes';
export type TipoDato = 'texto' | 'fecha' | 'fechaHora' | 'entero' | 'moneda';

export interface ColumnaReporte {
  clave: string;
  titulo: string;
  tipo: TipoDato;
  sumar?: boolean;
}

export interface TablaReporte {
  titulo: string;
  columnas: ColumnaReporte[];
  filas: Record<string, string | number | null>[];
}

export interface Reporte extends TablaReporte {
  tipo: TipoReporte;
  filtros: string;
  totales: { titulo: string; valor: number; tipo: TipoDato }[];
  resumen: TablaReporte[];
}

export interface UsuarioResumen {
  id: number;
  nombre: string;
}

export interface Dashboard {
  ventasMes: number;
  ticketsMes: number;
  gananciaMes: number;
  comprasMes: number;
  clientes: number;
  productos: number;
  stockBajo: { id: number; nombre: string; stock: number }[];
}
