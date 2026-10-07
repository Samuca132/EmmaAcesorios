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
  items?: TicketItem[];
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
