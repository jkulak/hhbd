#!/bin/bash
#
# HHBD - Populate Database Script
# Uploads an SQL dump and populates the database on the GCP VM
#
# Usage: ./05-populate-db.sh <path_to_sql_dump>
#

set -euo pipefail

# Configuration
PROJECT_ID="hhbd-483111"
ZONE="us-central1-a"
VM_NAME="hhbd-server"
REMOTE_DIR="/opt/hhbd"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

log_info() { echo -e "${GREEN}[INFO]${NC} $1"; }
log_warn() { echo -e "${YELLOW}[WARN]${NC} $1"; }
log_error() { echo -e "${RED}[ERROR]NC} $1"; }

# Check if gcloud is installed
if ! command -v gcloud &> /dev/null; then
    log_error "gcloud CLI is not installed. Please install it first."
    exit 1
fi

# Check for SQL dump file argument
if [ -z "$1" ]; then
    log_error "Usage: $0 <path_to_sql_dump>"
    exit 1
fi

DB_DUMP="$1"

if [ ! -f "$DB_DUMP" ]; then
    log_error "Database dump not found: $DB_DUMP"
    exit 1
fi

# Set project
gcloud config set project "${PROJECT_ID}" --quiet

# Upload database dump
log_info "Uploading database dump..."
gcloud compute scp --zone="${ZONE}" \
    "$DB_DUMP" "${VM_NAME}:${REMOTE_DIR}/init.sql"

# Import database
log_info "Waiting for database to be ready..."
sleep 15

log_info "Importing database..."
gcloud compute ssh "${VM_NAME}" --zone="${ZONE}" --command="
    cd ${REMOTE_DIR}
    docker compose -f compose.gcp.yaml exec -T db mysql -u hhbd -phhbd_password hhbd < init.sql
    rm init.sql
    echo 'Database import complete!'
"

echo ""
echo "=========================================="
echo -e "${GREEN}Database Population Complete${NC}"
echo "=========================================="
echo ""
