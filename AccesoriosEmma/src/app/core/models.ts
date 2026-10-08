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
  stockMinimo: number;
  /** stock ≤ stockMinimo */
  bajoMinimo: boolean;
  precio: number;
  coste: number;
  ganancia: number;
  /** Costo extra por unidad (mano de obra, packaging) al pasar a venta. */
  costoAdicional: number;
  /** Sin composición no se puede pasar a venta. */
  tieneComposicion: boolean;
}

export interface Insumo {
  id: number;
  nombre: string;
  stock: number;
  stockMinimo: number;
  bajoMinimo: boolean;
  precio: number;
  /** Lo que costó cada unidad (promedio ponderado de compras y canjes). */
  costoPromedio: number;
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

/** Datos comunes de las operaciones que se pueden anular (venta, compra, canje). */
export interface Anulable {
  anulado: boolean;
  anuladoAt: string | null;
  anuladoPor: string | null;
  motivoAnulacion: string | null;
  /** Lo decide el backend según la regla configurada (ANULACION_PERMITIDA). */
  puedeAnular: boolean;
}

export interface Compra extends Anulable {
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

export interface Canje extends Anulable {
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

export interface Ticket extends Anulable {
  id: number;
  fecha: string;
  clienteId: number;
  cliente: string;
  /** Para enviarle el comprobante por WhatsApp. */
  clienteTelefono: string | null;
  ciudad: string | null;
  cantidadProductos: number;
  total: number;
  usuario: string | null;
  items?: TicketItem[];
}

export interface Componente {
  insumoId: number;
  insumo: string;
  cantidad: number;
  stockInsumo: number;
  costoUnitario: number;
  insumoBorrado: boolean;
}

export interface Composicion {
  productoId: number;
  producto: string;
  costoAdicional: number;
  componentes: Componente[];
  /** Costo de una unidad con el costo actual de los insumos. */
  costoPorUnidad: number;
  costeActual: number;
}

export interface PaseVentaItem {
  productoId: number;
  producto: string;
  cantidad: number;
  costoUnitario: number;
  costoTotal: number;
}

export interface PaseVenta extends Anulable {
  id: number;
  fecha: string;
  nota: string | null;
  usuario: string | null;
  costoTotal: number;
  unidades: number;
  /** "10 × Collar, 5 × Aros" */
  productos: string;
  items?: PaseVentaItem[];
  consumos?: { insumoId: number; insumo: string; cantidad: number; costoUnitario: number }[];
}

export interface SimulacionPase {
  items: (PaseVentaItem & { costeActual: number })[];
  insumos: { insumoId: number; insumo: string; necesita: number; disponible: number; alcanza: boolean }[];
  costoTotal: number;
  problemas: string[];
}

export type TipoReporte = 'ventas' | 'compras' | 'canjes' | 'pases';
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

export interface Rol {
  id: number;
  nombre: string;
}

/** Usuario tal como lo ve un administrador en Configuración. */
export interface UsuarioAdmin {
  id: number;
  nombre: string;
  email: string;
  rol: number;
  rolNombre: string;
  activo: boolean;
  bloqueado: boolean;
  ultimoLogin: string | null;
  creado: string | null;
}

export interface NuevoUsuario {
  nombre: string;
  email: string;
  rol: number;
  password: string;
}

export interface UsuarioResumen {
  id: number;
  nombre: string;
}

export interface ArticuloBajo {
  id: number;
  nombre: string;
  stock: number;
  stockMinimo: number;
}

export interface Dashboard {
  ventasMes: number;
  ticketsMes: number;
  gananciaMes: number;
  comprasMes: number;
  clientes: number;
  productos: number;
  /** Productos para reponer (stock ≤ mínimo), los más urgentes primero. */
  stockBajo: ArticuloBajo[];
  /** Insumos para comprar. */
  insumosBajos: ArticuloBajo[];
}

export type AccionAuditoria = 'crear' | 'editar' | 'borrar' | 'anular';

/** Un registro del historial de cambios (Configuración → Historial). */
export interface RegistroAuditoria {
  id: number;
  fecha: string;
  usuario: string | null;
  ip: string | null;
  entidad: string;
  entidadId: number;
  descripcion: string;
  accion: AccionAuditoria;
  accionNombre: string;
  /** campo → [antes, después] */
  cambios: Record<string, [unknown, unknown]>;
  motivo: string | null;
}

export interface PaginaAuditoria {
  items: RegistroAuditoria[];
  total: number;
  pagina: number;
  porPagina: number;
  entidades: string[];
  acciones: { id: AccionAuditoria; nombre: string }[];
}

/** Datos de los gráficos del panel de inicio (mes en curso, sin anuladas). */
export interface Graficos {
  mes: string;
  mesAnterior: string;
  /** Acumulado día a día; actual es null en los días que todavía no llegaron. */
  ventasPorDia: { dia: number; actual: number | null; anterior: number | null }[];
  masVendidos: { nombre: string; unidades: number; total: number; ganancia: number }[];
  porUsuario: { nombre: string; tickets: number; total: number }[];
}
