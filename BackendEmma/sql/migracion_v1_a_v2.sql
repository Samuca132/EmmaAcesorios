-- =====================================================================
-- Emma Accesorios - Migración de la base existente (v1) al esquema v2
-- Pensado para MariaDB 10.4 (XAMPP).  HACÉ UN BACKUP ANTES DE CORRERLO:
--   mysqldump -u root emmaaccesorios > backup_emmaaccesorios.sql
-- =====================================================================

USE `emmaaccesorios`;

-- ---------------------------------------------------------------------
-- 1) Montos: de INT a DECIMAL para poder manejar centavos
-- ---------------------------------------------------------------------
ALTER TABLE `producto`
  MODIFY `NombreProducto`  VARCHAR(50) NOT NULL DEFAULT '',
  MODIFY `PrecioProducto`  DECIMAL(12,2) NOT NULL DEFAULT 0,
  MODIFY `costeProduccion` DECIMAL(12,2) NOT NULL DEFAULT 0;

ALTER TABLE `insumo`
  MODIFY `precio` DECIMAL(12,2) NOT NULL DEFAULT 0,
  MODIFY `DescuentoPactadoCanje` INT NOT NULL DEFAULT 0;

ALTER TABLE `compras`  MODIFY `costo`  DECIMAL(12,2) NOT NULL;
ALTER TABLE `canjes`   MODIFY `Profit` DECIMAL(12,2) NOT NULL DEFAULT 0;

-- ---------------------------------------------------------------------
-- 2) Imágenes: ya no se usan. Se vuelven opcionales para que los altas
--    nuevas no fallen. (Al final del archivo está, comentado, cómo
--    borrarlas definitivamente.)
-- ---------------------------------------------------------------------
ALTER TABLE `producto` MODIFY `ImagenProducto` LONGBLOB NULL;
ALTER TABLE `insumo`   MODIFY `ImagenInsumo`   LONGBLOB NULL;
ALTER TABLE `cliente`  MODIFY `Imagen`         LONGBLOB NULL;

-- ---------------------------------------------------------------------
-- 3) Tipos de las claves foráneas (estaban como VARCHAR)
-- ---------------------------------------------------------------------
ALTER TABLE `cliente` MODIFY `IDCiudad` VARCHAR(6) NULL;
UPDATE `cliente` SET `IDCiudad` = NULL
 WHERE `IDCiudad` NOT REGEXP '^[0-9]+$'
    OR `IDCiudad` NOT IN (SELECT `IDCiudad` FROM `ciudad`);
ALTER TABLE `cliente`
  MODIFY `nombreCliente`   VARCHAR(50) NOT NULL,
  MODIFY `IDCiudad`        INT NULL,
  MODIFY `telefonoCliente` VARCHAR(20) NOT NULL DEFAULT '';

ALTER TABLE `proveedores`
  MODIFY `IDCiudad` INT NULL,
  MODIFY `TelefonoProveedor` VARCHAR(20) NOT NULL DEFAULT '';
UPDATE `proveedores` SET `IDCiudad` = NULL
 WHERE `IDCiudad` NOT IN (SELECT `IDCiudad` FROM `ciudad`);

ALTER TABLE `ciudad`
  MODIFY `NombreCiudad` VARCHAR(50) NOT NULL;

-- ---------------------------------------------------------------------
-- 4) Tickets de venta (el frontend los usaba pero no estaban en el dump)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `ticket` (
  `IDTicket`   INT NOT NULL AUTO_INCREMENT,
  `IDCliente`  INT NOT NULL,
  `Fecha`      DATETIME NOT NULL,
  `CProductos` INT NOT NULL DEFAULT 0,
  `Valor`      DECIMAL(12,2) NOT NULL DEFAULT 0,
  PRIMARY KEY (`IDTicket`),
  KEY `idx_ticket_cliente` (`IDCliente`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE `ticket` MODIFY `Valor` DECIMAL(12,2) NOT NULL DEFAULT 0;

ALTER TABLE `venta`
  ADD COLUMN IF NOT EXISTS `IDTicket` INT NULL AFTER `IDVenta`,
  ADD COLUMN IF NOT EXISTS `PrecioUnitario` DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER `CantidadProducto`;

ALTER TABLE `venta`
  MODIFY `IDCliente`  INT NOT NULL,
  MODIFY `IDProducto` INT NOT NULL,
  MODIFY `profit`     DECIMAL(12,2) NOT NULL DEFAULT 0,
  MODIFY `Total`      DECIMAL(12,2) NOT NULL DEFAULT 0;

-- Ventas viejas que no tienen ticket: se crea un ticket por cada una para
-- que aparezcan en el listado de ventas.
ALTER TABLE `ticket` ADD COLUMN `tmp_IDVenta` INT NULL;
INSERT INTO `ticket` (`IDCliente`, `Fecha`, `CProductos`, `Valor`, `tmp_IDVenta`)
  SELECT `IDCliente`, `fechaVenta`, `CantidadProducto`, `Total`, `IDVenta`
    FROM `venta` WHERE `IDTicket` IS NULL;
UPDATE `venta` v JOIN `ticket` t ON t.`tmp_IDVenta` = v.`IDVenta` SET v.`IDTicket` = t.`IDTicket`;
ALTER TABLE `ticket` DROP COLUMN `tmp_IDVenta`;

UPDATE `venta` SET `PrecioUnitario` = ROUND(`Total` / `CantidadProducto`, 2)
 WHERE `PrecioUnitario` = 0 AND `CantidadProducto` > 0;

-- ---------------------------------------------------------------------
-- 5) Usuarios: contraseñas hasheadas + protección contra fuerza bruta
-- ---------------------------------------------------------------------
ALTER TABLE `usuario`
  MODIFY `NombreUsuario` VARCHAR(50) NOT NULL,
  CHANGE `PaswordUsuario` `PasswordHash` VARCHAR(255) NOT NULL,
  ADD COLUMN IF NOT EXISTS `Activo`           TINYINT(1) NOT NULL DEFAULT 1,
  ADD COLUMN IF NOT EXISTS `IntentosFallidos` INT NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS `BloqueadoHasta`   DATETIME NULL,
  ADD COLUMN IF NOT EXISTS `UltimoLogin`      DATETIME NULL,
  ADD COLUMN IF NOT EXISTS `FechaCreacion`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP;

-- Las contraseñas viejas estaban guardadas en texto plano: se borran.
-- Después de migrar, regenerá cada contraseña con:
--   php bin/console app:usuario:password usuario@ejemplo.com
-- o creá un usuario nuevo con:
--   php bin/console app:usuario:crear
UPDATE `usuario` SET `PasswordHash` = '!' WHERE `PasswordHash` NOT LIKE '$2y$%';

-- ---------------------------------------------------------------------
-- 6) (OPCIONAL) Borrar definitivamente las imágenes y la tabla de pruebas.
--    Descomentar solo si ya no las vas a necesitar.
-- ---------------------------------------------------------------------
-- ALTER TABLE `producto` DROP COLUMN `ImagenProducto`;
-- ALTER TABLE `insumo`   DROP COLUMN `ImagenInsumo`;
-- ALTER TABLE `cliente`  DROP COLUMN `Imagen`;
-- DROP TABLE IF EXISTS `testing`;
