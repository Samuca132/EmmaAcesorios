-- =====================================================================
-- Emma Accesorios - Migración v2 → v3: timestamps y borrado lógico
-- Pensado para MariaDB 10.4+ (XAMPP / Ubuntu).
--
-- Agrega created_at, updated_at y deleted_at a todas las tablas y pasa
-- los registros que estaban "ocultos" (visibility = 0) a borrados
-- (deleted_at con fecha). Después Doctrine completa el resto.
--
-- Pasos:
--   1) Backup:   mysqldump -u root emmaaccesorios > backup_antes_v3.sql
--   2) Este archivo (UNA sola vez):
--                mysql -u root emmaaccesorios < sql/migracion_v2_a_v3.sql
--   3) php bin/console doctrine:schema:update --dump-sql   (revisar)
--      php bin/console doctrine:schema:update --force
--      (borra la columna visibility, que ya no se usa)
--   4) php bin/console doctrine:schema:validate
--
-- Si venís de la base original (v1), primero corré migracion_v1_a_v2.sql.
-- =====================================================================

USE `emmaaccesorios`;


ALTER TABLE `ciudad`
  ADD COLUMN IF NOT EXISTS `created_at` DATETIME NULL,
  ADD COLUMN IF NOT EXISTS `updated_at` DATETIME NULL,
  ADD COLUMN IF NOT EXISTS `deleted_at` DATETIME NULL;
UPDATE `ciudad` SET `created_at` = COALESCE(NOW(), NOW()) WHERE `created_at` IS NULL;
UPDATE `ciudad` SET `updated_at` = `created_at` WHERE `updated_at` IS NULL;

ALTER TABLE `cliente`
  ADD COLUMN IF NOT EXISTS `created_at` DATETIME NULL,
  ADD COLUMN IF NOT EXISTS `updated_at` DATETIME NULL,
  ADD COLUMN IF NOT EXISTS `deleted_at` DATETIME NULL;
UPDATE `cliente` SET `created_at` = COALESCE(NOW(), NOW()) WHERE `created_at` IS NULL;
UPDATE `cliente` SET `updated_at` = `created_at` WHERE `updated_at` IS NULL;

ALTER TABLE `proveedores`
  ADD COLUMN IF NOT EXISTS `created_at` DATETIME NULL,
  ADD COLUMN IF NOT EXISTS `updated_at` DATETIME NULL,
  ADD COLUMN IF NOT EXISTS `deleted_at` DATETIME NULL;
UPDATE `proveedores` SET `created_at` = COALESCE(NOW(), NOW()) WHERE `created_at` IS NULL;
UPDATE `proveedores` SET `updated_at` = `created_at` WHERE `updated_at` IS NULL;

ALTER TABLE `producto`
  ADD COLUMN IF NOT EXISTS `created_at` DATETIME NULL,
  ADD COLUMN IF NOT EXISTS `updated_at` DATETIME NULL,
  ADD COLUMN IF NOT EXISTS `deleted_at` DATETIME NULL;
UPDATE `producto` SET `created_at` = COALESCE(NOW(), NOW()) WHERE `created_at` IS NULL;
UPDATE `producto` SET `updated_at` = `created_at` WHERE `updated_at` IS NULL;

ALTER TABLE `insumo`
  ADD COLUMN IF NOT EXISTS `created_at` DATETIME NULL,
  ADD COLUMN IF NOT EXISTS `updated_at` DATETIME NULL,
  ADD COLUMN IF NOT EXISTS `deleted_at` DATETIME NULL;
UPDATE `insumo` SET `created_at` = COALESCE(NOW(), NOW()) WHERE `created_at` IS NULL;
UPDATE `insumo` SET `updated_at` = `created_at` WHERE `updated_at` IS NULL;

ALTER TABLE `compras`
  ADD COLUMN IF NOT EXISTS `created_at` DATETIME NULL,
  ADD COLUMN IF NOT EXISTS `updated_at` DATETIME NULL,
  ADD COLUMN IF NOT EXISTS `deleted_at` DATETIME NULL;
UPDATE `compras` SET `created_at` = COALESCE(`FechaCompra`, NOW()) WHERE `created_at` IS NULL;
UPDATE `compras` SET `updated_at` = `created_at` WHERE `updated_at` IS NULL;

ALTER TABLE `canjes`
  ADD COLUMN IF NOT EXISTS `created_at` DATETIME NULL,
  ADD COLUMN IF NOT EXISTS `updated_at` DATETIME NULL,
  ADD COLUMN IF NOT EXISTS `deleted_at` DATETIME NULL;
UPDATE `canjes` SET `created_at` = COALESCE(`FechaCanje`, NOW()) WHERE `created_at` IS NULL;
UPDATE `canjes` SET `updated_at` = `created_at` WHERE `updated_at` IS NULL;

ALTER TABLE `ticket`
  ADD COLUMN IF NOT EXISTS `created_at` DATETIME NULL,
  ADD COLUMN IF NOT EXISTS `updated_at` DATETIME NULL,
  ADD COLUMN IF NOT EXISTS `deleted_at` DATETIME NULL;
UPDATE `ticket` SET `created_at` = COALESCE(`Fecha`, NOW()) WHERE `created_at` IS NULL;
UPDATE `ticket` SET `updated_at` = `created_at` WHERE `updated_at` IS NULL;

ALTER TABLE `venta`
  ADD COLUMN IF NOT EXISTS `created_at` DATETIME NULL,
  ADD COLUMN IF NOT EXISTS `updated_at` DATETIME NULL,
  ADD COLUMN IF NOT EXISTS `deleted_at` DATETIME NULL;
UPDATE `venta` SET `created_at` = COALESCE(`fechaVenta`, NOW()) WHERE `created_at` IS NULL;
UPDATE `venta` SET `updated_at` = `created_at` WHERE `updated_at` IS NULL;

-- Lo que antes estaba oculto (baja lógica con visibility = 0) pasa a estar borrado
UPDATE `cliente`  SET `deleted_at` = NOW() WHERE `visibility` = 0 AND `deleted_at` IS NULL;
UPDATE `producto` SET `deleted_at` = NOW() WHERE `visibility` = 0 AND `deleted_at` IS NULL;
UPDATE `insumo`   SET `deleted_at` = NOW() WHERE `visibility` = 0 AND `deleted_at` IS NULL;

-- Usuarios: FechaCreacion pasa a ser created_at
ALTER TABLE `usuario`
  CHANGE COLUMN IF EXISTS `FechaCreacion` `created_at` DATETIME NULL,
  ADD COLUMN IF NOT EXISTS `updated_at` DATETIME NULL,
  ADD COLUMN IF NOT EXISTS `deleted_at` DATETIME NULL;
UPDATE `usuario` SET `created_at` = NOW() WHERE `created_at` IS NULL;
UPDATE `usuario` SET `updated_at` = `created_at` WHERE `updated_at` IS NULL;
