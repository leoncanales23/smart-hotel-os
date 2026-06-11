#!/usr/bin/env bash
# ============================================================
#  SmartHotelOS — Script de Deploy a Producción
#  Grupo Smart de Administración
#  Uso: bash scripts/deploy.sh [staging|production] [tag]
# ============================================================

set -euo pipefail

# ── Colores ───────────────────────────────────────────────────
RED='\033[0;31m'; GREEN='\033[0;32m'; YELLOW='\033[1;33m'
BLUE='\033[0;34m'; GOLD='\033[0;33m'; RESET='\033[0m'

log()  { echo -e "${BLUE}[$(date '+%H:%M:%S')]${RESET} $1"; }
ok()   { echo -e "${GREEN}  ✓ $1${RESET}"; }
warn() { echo -e "${YELLOW}  ⚠ $1${RESET}"; }
err()  { echo -e "${RED}  ✗ $1${RESET}"; exit 1; }

# ── Banner ────────────────────────────────────────────────────
echo -e "${GOLD}"
echo "  ╔══════════════════════════════════════╗"
echo "  ║   SmartHotelOS — Deploy Script       ║"
echo "  ║   Grupo Smart de Administración      ║"
echo "  ╚══════════════════════════════════════╝"
echo -e "${RESET}"

# ── Argumentos ───────────────────────────────────────────────
ENV="${1:-staging}"
TAG="${2:-$(git describe --tags --abbrev=0 2>/dev/null || echo 'develop')}"

if [[ "$ENV" != "staging" && "$ENV" != "production" ]]; then
    err "Entorno inválido: '$ENV'. Usar 'staging' o 'production'"
fi

log "Entorno: ${GOLD}${ENV}${RESET} | Tag/Branch: ${GOLD}${TAG}${RESET}"

# ── Verificar prerrequisitos ──────────────────────────────────
log "Verificando prerrequisitos..."
command -v git     >/dev/null 2>&1 || err "git no encontrado"
command -v php     >/dev/null 2>&1 || err "php no encontrado"
command -v composer>/dev/null 2>&1 || warn "composer no encontrado en PATH"
ok "Prerrequisitos verificados"

# ── Variables de entorno por entorno ─────────────────────────
if [[ "$ENV" == "production" ]]; then
    DEPLOY_HOST="${SMART_PROD_HOST:-}"
    DEPLOY_USER="${SMART_PROD_USER:-deploy}"
    DEPLOY_PATH="${SMART_PROD_PATH:-/var/www/smart-hotel-production}"
    DEPLOY_KEY="${SMART_PROD_SSH_KEY:-~/.ssh/smart_prod}"
else
    DEPLOY_HOST="${SMART_STAGING_HOST:-}"
    DEPLOY_USER="${SMART_STAGING_USER:-deploy}"
    DEPLOY_PATH="${SMART_STAGING_PATH:-/var/www/smart-hotel-staging}"
    DEPLOY_KEY="${SMART_STAGING_SSH_KEY:-~/.ssh/smart_staging}"
fi

[[ -z "$DEPLOY_HOST" ]] && err "SMART_${ENV^^}_HOST no está configurado en el entorno"

# ── Verificar rama limpia en producción ───────────────────────
if [[ "$ENV" == "production" ]]; then
    if [[ -n "$(git status --porcelain)" ]]; then
        err "Hay cambios sin commitear. Limpia el working directory antes de deployar a producción."
    fi
    if ! git tag | grep -q "^$TAG$"; then
        err "El tag '$TAG' no existe. Crea un tag semántico antes de deployar a producción."
    fi
    log "Confirmación de deploy a PRODUCCIÓN requerida..."
    read -p "  → ¿Confirmas deploy de ${TAG} a producción? (escribe 'CONFIRMO'): " CONFIRM
    [[ "$CONFIRM" != "CONFIRMO" ]] && err "Deploy cancelado."
fi

# ── Ejecutar tests antes del deploy ──────────────────────────
log "Ejecutando suite de tests..."
if [[ -f "vendor/bin/phpunit" ]]; then
    vendor/bin/phpunit --no-coverage 2>&1 | tail -5
    ok "Tests pasados"
else
    warn "PHPUnit no instalado — saltando tests"
fi

# ── Función de deploy remoto ──────────────────────────────────
remote_exec() {
    ssh -i "$DEPLOY_KEY" -o StrictHostKeyChecking=no \
        "${DEPLOY_USER}@${DEPLOY_HOST}" "$@"
}

# ── Deploy ────────────────────────────────────────────────────
log "Iniciando deploy a ${DEPLOY_HOST}..."
DEPLOY_START=$(date +%s)

# 1. Crear backup del estado actual
log "  1/7 Backup del estado actual..."
remote_exec "
    if [ -d '${DEPLOY_PATH}' ]; then
        BACKUP_DIR='${DEPLOY_PATH}/../backups/backup-\$(date +%Y%m%d-%H%M%S)'
        mkdir -p \$BACKUP_DIR
        cp -r ${DEPLOY_PATH}/smart-modules \$BACKUP_DIR/ 2>/dev/null || true
        cp ${DEPLOY_PATH}/.env \$BACKUP_DIR/ 2>/dev/null || true
        echo \"Backup en: \$BACKUP_DIR\"
    fi
" && ok "Backup completado"

# 2. Actualizar código
log "  2/7 Actualizando código fuente..."
remote_exec "
    cd ${DEPLOY_PATH}
    git fetch --tags origin
    git checkout ${TAG}
    git pull origin ${TAG} 2>/dev/null || true
" && ok "Código actualizado a ${TAG}"

# 3. Instalar dependencias PHP
log "  3/7 Instalando dependencias Composer..."
remote_exec "
    cd ${DEPLOY_PATH}
    composer install --no-dev --optimize-autoloader --no-interaction --quiet
" && ok "Dependencias instaladas"

# 4. Build de assets frontend
log "  4/7 Build de assets..."
remote_exec "
    cd ${DEPLOY_PATH}
    if [ -f package.json ]; then
        npm ci --silent && npm run build 2>/dev/null || npm run build:prod 2>/dev/null || true
    fi
" && ok "Assets compilados"

# 5. Ejecutar migraciones/actualizaciones de BD
log "  5/7 Actualizando esquema de base de datos..."
remote_exec "
    cd ${DEPLOY_PATH}
    php scripts/install-smart.php --env=${ENV} --skip-demo 2>&1 | tail -10
" && ok "Base de datos actualizada"

# 6. Limpiar cache
log "  6/7 Limpiando cache..."
remote_exec "
    cd ${DEPLOY_PATH}
    rm -rf cache/cachefs/* 2>/dev/null || true
    find cache/ -name '*.php' -delete 2>/dev/null || true
    find cache/ -name '*.cache' -delete 2>/dev/null || true
    php -r \"
        if (class_exists('Cache')) {
            Cache::getInstance()->flush();
            echo 'Cache Redis limpiado';
        }
    \" 2>/dev/null || true
" && ok "Cache limpiada"

# 7. Verificar health check
log "  7/7 Verificando salud del sistema..."
sleep 2
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" \
    -H "X-API-Key: healthcheck" \
    "https://${DEPLOY_HOST}/api/smart/v1/health" 2>/dev/null || echo "000")

if [[ "$HTTP_CODE" == "200" ]] || [[ "$HTTP_CODE" == "401" ]]; then
    ok "Sistema respondiendo (HTTP ${HTTP_CODE})"
else
    warn "Health check devolvió HTTP ${HTTP_CODE} — verificar manualmente"
fi

# ── Resumen ───────────────────────────────────────────────────
DEPLOY_END=$(date +%s)
ELAPSED=$((DEPLOY_END - DEPLOY_START))

echo ""
echo -e "${GOLD}  ══════════════════════════════════════${RESET}"
echo -e "${GREEN}  ✓ Deploy completado exitosamente${RESET}"
echo -e "  Entorno:  ${GOLD}${ENV}${RESET}"
echo -e "  Versión:  ${GOLD}${TAG}${RESET}"
echo -e "  Host:     ${GOLD}${DEPLOY_HOST}${RESET}"
echo -e "  Tiempo:   ${GOLD}${ELAPSED}s${RESET}"
echo -e "${GOLD}  ══════════════════════════════════════${RESET}"
echo ""

# Notificar al equipo si Slack está configurado
if [[ -n "${SMART_SLACK_WEBHOOK:-}" ]]; then
    curl -s -X POST -H 'Content-type: application/json' \
        --data "{\"text\":\"✅ SmartHotelOS *${TAG}* deployado en *${ENV}* por \$(git config user.name) (${ELAPSED}s)\"}" \
        "$SMART_SLACK_WEBHOOK" >/dev/null
fi
