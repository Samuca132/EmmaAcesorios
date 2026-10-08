#!/usr/bin/env bash
#
# Arma el sitio completo (frontend + backend) en deploy/build/htdocs y, si
# está instalado lftp y existe deploy/deploy.env, lo sube por FTP.
#
#   ./deploy/deploy.sh            # armar y subir
#   ./deploy/deploy.sh --solo-armar   # solo armar (para subir con FileZilla)
#
# Solo sube los archivos que cambiaron desde la última vez y, al final, borra
# la caché de Symfony en el servidor (sin eso el backend seguiría usando las
# rutas y la configuración viejas). Nunca pisa ni borra en el servidor:
# backend/.env, backend/app/config/parameters.yml ni backend/var/.

set -euo pipefail

RAIZ="$(cd "$(dirname "$0")/.." && pwd)"
DEPLOY="$RAIZ/deploy"
TMP="$DEPLOY/build/tmp"
SITIO="$DEPLOY/build/htdocs"

paso() { printf '\n\033[1;35m▶ %s\033[0m\n' "$1"; }

paso "Pruebas"
(cd "$RAIZ/BackendEmma" && vendor/bin/phpunit)
(cd "$RAIZ/AccesoriosEmma" && npm test --silent)

paso "Frontend (ng build)"
(cd "$RAIZ/AccesoriosEmma" && npx ng build)

paso "Backend (sin dependencias de desarrollo)"
rm -rf "$TMP" && mkdir -p "$TMP/backend"
rsync -a "$RAIZ/BackendEmma/" "$TMP/backend/" \
    --exclude vendor --exclude var --exclude tests --exclude .env \
    --exclude app/config/parameters.yml --exclude phpunit.xml.dist --exclude .phpunit.result.cache \
    --exclude .web-server-pid --exclude '*.md'
(cd "$TMP/backend" && composer install --no-dev --classmap-authoritative --no-scripts --no-interaction --quiet)
# Las pruebas y la documentación de las librerías no se usan en producción y son
# casi la mitad de los archivos (InfinityFree limita la cantidad y FTP es lento)
find "$TMP/backend/vendor" -depth -type d \( -name Tests -o -name tests -o -name doc -o -name docs \) -exec rm -rf {} +
find "$TMP/backend/vendor" -type f \( -name '*.md' -o -name 'phpunit.xml*' -o -name '.travis.yml' \) -delete

paso "Armando el sitio"
cp -a "$RAIZ/AccesoriosEmma/dist/AccesoriosEmma/browser/." "$TMP/"
cp "$DEPLOY/plantillas/htdocs.htaccess" "$TMP/.htaccess"
for carpeta in app bin sql src vendor; do
    cp "$DEPLOY/plantillas/denegar.htaccess" "$TMP/backend/$carpeta/.htaccess"
done
mkdir -p "$TMP/backend/var" && cp "$DEPLOY/plantillas/denegar.htaccess" "$TMP/backend/var/.htaccess"
cp "$DEPLOY/plantillas/backend-raiz.htaccess" "$TMP/backend/.htaccess"

# Copia "inteligente": los archivos que no cambiaron conservan su fecha,
# así lftp sube solo lo nuevo
mkdir -p "$SITIO"
rsync -rc --delete "$TMP/" "$SITIO/"
rm -rf "$TMP"
echo "Sitio listo en $SITIO ($(find "$SITIO" -type f | wc -l) archivos)"

if [[ "${1:-}" == "--solo-armar" ]]; then
    exit 0
fi
if ! command -v lftp >/dev/null; then
    echo "lftp no está instalado (sudo apt install lftp). Subí el contenido de $SITIO con FileZilla"
    echo "y después borrá la carpeta backend/var/cache/prod del servidor."
    exit 0
fi
if [[ ! -f "$DEPLOY/deploy.env" ]]; then
    echo "Falta deploy/deploy.env (copiar de deploy/deploy.env.dist)."
    exit 1
fi
# shellcheck disable=SC1091
source "$DEPLOY/deploy.env"

paso "Subiendo a $FTP_HOST$FTP_DIR"
lftp -u "$FTP_USER","$FTP_PASSWORD" "$FTP_HOST" <<LFTP
set ftp:ssl-allow yes
set net:max-retries 3
set net:timeout 30
mirror --reverse --only-newer --delete --parallel=4 --verbose=1 \
    --exclude-glob backend/.env \
    --exclude-glob backend/app/config/parameters.yml \
    --exclude backend/var/ \
    "$SITIO" "$FTP_DIR"
mkdir -p -f "$FTP_DIR/backend/var/cache" "$FTP_DIR/backend/var/logs"
rm -r -f "$FTP_DIR/backend/var/cache/prod"
bye
LFTP

echo
echo "Listo. Si este deploy trae un script nuevo en BackendEmma/sql/, ejecutalo en phpMyAdmin."
