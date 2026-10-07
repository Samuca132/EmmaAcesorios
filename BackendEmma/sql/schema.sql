-- =====================================================================
-- Emma Accesorios - Esquema v2 (instalación desde cero)
-- Compatible con MariaDB 10.4+ / MySQL 5.7+
-- Si ya tenés datos cargados con la versión anterior usá
-- migracion_v1_a_v2.sql en lugar de este archivo.
-- =====================================================================

CREATE DATABASE IF NOT EXISTS `emmaaccesorios`
  DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `emmaaccesorios`;

SET NAMES utf8mb4;

CREATE TABLE `ciudad` (
  `IDCiudad`     INT NOT NULL AUTO_INCREMENT,
  `NombreCiudad` VARCHAR(50) NOT NULL,
  `Provincia`    INT NOT NULL,
  PRIMARY KEY (`IDCiudad`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `cliente` (
  `IDCliente`       INT NOT NULL AUTO_INCREMENT,
  `nombreCliente`   VARCHAR(50) NOT NULL,
  `IDCiudad`        INT NULL,
  `telefonoCliente` VARCHAR(20) NOT NULL DEFAULT '',
  `visibility`      TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`IDCliente`),
  KEY `idx_cliente_ciudad` (`IDCiudad`),
  CONSTRAINT `fk_cliente_ciudad` FOREIGN KEY (`IDCiudad`) REFERENCES `ciudad` (`IDCiudad`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `proveedores` (
  `IDProveedor`       INT NOT NULL AUTO_INCREMENT,
  `nombre`            VARCHAR(50) NOT NULL,
  `IDCiudad`          INT NULL,
  `TelefonoProveedor` VARCHAR(20) NOT NULL DEFAULT '',
  PRIMARY KEY (`IDProveedor`),
  KEY `idx_proveedor_ciudad` (`IDCiudad`),
  CONSTRAINT `fk_proveedor_ciudad` FOREIGN KEY (`IDCiudad`) REFERENCES `ciudad` (`IDCiudad`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `producto` (
  `IDProducto`      INT NOT NULL AUTO_INCREMENT,
  `NombreProducto`  VARCHAR(50) NOT NULL,
  `stockProducto`   INT NOT NULL DEFAULT 0,
  `PrecioProducto`  DECIMAL(12,2) NOT NULL DEFAULT 0,
  `costeProduccion` DECIMAL(12,2) NOT NULL DEFAULT 0,
  `visibility`      TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`IDProducto`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `insumo` (
  `IDInsumo`              INT NOT NULL AUTO_INCREMENT,
  `NombreInsumo`          VARCHAR(50) NOT NULL,
  `Stock`                 INT NOT NULL DEFAULT 0,
  `precio`                DECIMAL(12,2) NOT NULL DEFAULT 0,
  `DescuentoPactadoCanje` INT NOT NULL DEFAULT 0,
  `visibility`            TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`IDInsumo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `compras` (
  `IDCompra`    INT NOT NULL AUTO_INCREMENT,
  `FechaCompra` DATE NOT NULL,
  `IDProveedor` INT NOT NULL,
  `IDInsumo`    INT NOT NULL,
  `cantidad`    INT NOT NULL,
  `costo`       DECIMAL(12,2) NOT NULL,
  PRIMARY KEY (`IDCompra`),
  KEY `idx_compra_proveedor` (`IDProveedor`),
  KEY `idx_compra_insumo` (`IDInsumo`),
  CONSTRAINT `fk_compra_proveedor` FOREIGN KEY (`IDProveedor`) REFERENCES `proveedores` (`IDProveedor`),
  CONSTRAINT `fk_compra_insumo` FOREIGN KEY (`IDInsumo`) REFERENCES `insumo` (`IDInsumo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `canjes` (
  `IDCanje`          INT NOT NULL AUTO_INCREMENT,
  `IDProducto`       INT NOT NULL,
  `IDProveedor`      INT NOT NULL,
  `IDInsumo`         INT NOT NULL,
  `FechaCanje`       DATE NOT NULL,
  `Profit`           DECIMAL(12,2) NOT NULL DEFAULT 0,
  `CantidadProducto` INT NOT NULL,
  `CantidadInsumo`   INT NOT NULL,
  PRIMARY KEY (`IDCanje`),
  CONSTRAINT `fk_canje_producto` FOREIGN KEY (`IDProducto`) REFERENCES `producto` (`IDProducto`),
  CONSTRAINT `fk_canje_proveedor` FOREIGN KEY (`IDProveedor`) REFERENCES `proveedores` (`IDProveedor`),
  CONSTRAINT `fk_canje_insumo` FOREIGN KEY (`IDInsumo`) REFERENCES `insumo` (`IDInsumo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Un ticket agrupa los productos vendidos a un cliente en una misma venta.
CREATE TABLE `ticket` (
  `IDTicket`   INT NOT NULL AUTO_INCREMENT,
  `IDCliente`  INT NOT NULL,
  `Fecha`      DATETIME NOT NULL,
  `CProductos` INT NOT NULL DEFAULT 0,
  `Valor`      DECIMAL(12,2) NOT NULL DEFAULT 0,
  PRIMARY KEY (`IDTicket`),
  KEY `idx_ticket_cliente` (`IDCliente`),
  CONSTRAINT `fk_ticket_cliente` FOREIGN KEY (`IDCliente`) REFERENCES `cliente` (`IDCliente`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Cada renglón de un ticket.
CREATE TABLE `venta` (
  `IDVenta`          INT NOT NULL AUTO_INCREMENT,
  `IDTicket`         INT NULL,
  `IDCliente`        INT NOT NULL,
  `IDProducto`       INT NOT NULL,
  `CantidadProducto` INT NOT NULL,
  `PrecioUnitario`   DECIMAL(12,2) NOT NULL DEFAULT 0,
  `profit`           DECIMAL(12,2) NOT NULL DEFAULT 0,
  `fechaVenta`       DATE NOT NULL,
  `Total`            DECIMAL(12,2) NOT NULL DEFAULT 0,
  PRIMARY KEY (`IDVenta`),
  KEY `idx_venta_ticket` (`IDTicket`),
  KEY `idx_venta_cliente` (`IDCliente`),
  KEY `idx_venta_producto` (`IDProducto`),
  CONSTRAINT `fk_venta_ticket` FOREIGN KEY (`IDTicket`) REFERENCES `ticket` (`IDTicket`),
  CONSTRAINT `fk_venta_cliente` FOREIGN KEY (`IDCliente`) REFERENCES `cliente` (`IDCliente`),
  CONSTRAINT `fk_venta_producto` FOREIGN KEY (`IDProducto`) REFERENCES `producto` (`IDProducto`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Usuarios del sistema. La contraseña se guarda SIEMPRE como hash bcrypt.
CREATE TABLE `usuario` (
  `IDUsuario`        INT NOT NULL AUTO_INCREMENT,
  `NombreUsuario`    VARCHAR(50) NOT NULL,
  `Rol`              INT NOT NULL DEFAULT 2,          -- 1 = administrador, 2 = usuario
  `UsuarioEmail`     VARCHAR(100) NOT NULL,
  `PasswordHash`     VARCHAR(255) NOT NULL,
  `Activo`           TINYINT(1) NOT NULL DEFAULT 1,
  `IntentosFallidos` INT NOT NULL DEFAULT 0,
  `BloqueadoHasta`   DATETIME NULL,
  `UltimoLogin`      DATETIME NULL,
  `FechaCreacion`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`IDUsuario`),
  UNIQUE KEY `uq_usuario_email` (`UsuarioEmail`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
