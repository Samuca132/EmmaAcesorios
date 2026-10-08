#!/usr/bin/env bash
# Lleva la base LOCAL a v4: completa fechas vacías, corre la migración v3→v4,
# termina el ajuste de v3 (fechas obligatorias) y limpia la caché.
# Uso:  bash sql/actualizar_local_a_v4.sh   (pide la contraseña de MySQL una vez)
set -euo pipefail
cd "$(dirname "$0")/.."

BASE=${BASE:-emmaaccesorios}
USUARIO=${USUARIO:-root}
read -rsp "Contraseña de MySQL para $USUARIO (Enter si no tiene): " CLAVE; echo
export MYSQL_PWD="$CLAVE"
M="mysql -u $USUARIO $BASE"

echo "1/5 Backup → backup_antes_v4.sql"
mysqldump -u "$USUARIO" --single-transaction "$BASE" > backup_antes_v4.sql

echo "2/5 Completando fechas vacías"
for t in ciudad cliente compras insumo producto proveedores ticket canjes venta usuario; do
  $M -e "UPDATE $t SET created_at = COALESCE(created_at, updated_at, NOW()) WHERE created_at IS NULL;
         UPDATE $t SET updated_at = COALESCE(updated_at, created_at, NOW()) WHERE updated_at IS NULL;"
done

echo "3/5 Migración v3 → v4"
$M < sql/migracion_v3_a_v4.sql

echo "4/5 Ajuste final del esquema"
php bin/console doctrine:schema:update --force
php bin/console doctrine:schema:validate

echo "5/5 Limpiando caché"
php bin/console cache:clear --env=prod

echo "Listo. Recargá el navegador."
