#!/bin/bash
#
# HHBD - Deploy to Server
# Run this script LOCALLY to deploy the application to the GCP VM
#
# Prerequisites:
#   - SSH access to the VM (gcloud compute ssh)
#   - Images built and pushed (03-build-push.sh)
#   - Server set up (02-setup-server.sh)
#
# Usage:
#   ./04-deploy.sh                              # Deploy all services
#   ./04-deploy.sh app                          # Deploy only app service
#   ./04-deploy.sh nginx                        # Deploy only nginx service
#   ./04-deploy.sh backoffice                   # Deploy only backoffice service
#   ./04-deploy.sh app nginx                    # Deploy app and nginx services
#

set -euo pipefail

# Configuration
PROJECT_ID="hhbd-483111"
ZONE="us-central1-a"
VM_NAME="hhbd-server"
REMOTE_DIR="/opt/hhbd"

# Get script directory
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

# Parse arguments
DEPLOY_APP=false
DEPLOY_NGINX=false
DEPLOY_BACKOFFICE=false
SERVICES_SPECIFIED=false

while [[ $# -gt 0 ]]; do
    case $1 in
        app)
            DEPLOY_APP=true
            SERVICES_SPECIFIED=true
            shift
            ;;
        nginx)
            DEPLOY_NGINX=true
            SERVICES_SPECIFIED=true
            shift
            ;;
        backoffice)
            DEPLOY_BACKOFFICE=true
            SERVICES_SPECIFIED=true
            shift
            ;;
        *)
            echo "Unknown option: $1"
            echo "Usage: $0 [app] [nginx] [backoffice]"
            exit 1
            ;;
    esac
done

# If no services specified, deploy all
if [ "$SERVICES_SPECIFIED" = false ]; then
    DEPLOY_APP=true
    DEPLOY_NGINX=true
    DEPLOY_BACKOFFICE=true
fi

# Build list of services to deploy
SERVICES=""
if [ "$DEPLOY_APP" = true ]; then
    SERVICES="${SERVICES} app"
fi
if [ "$DEPLOY_NGINX" = true ]; then
    SERVICES="${SERVICES} nginx"
fi
if [ "$DEPLOY_BACKOFFICE" = true ]; then
    SERVICES="${SERVICES} backoffice"
fi
SERVICES=$(echo "$SERVICES" | xargs)  # Trim whitespace

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

log_info() { echo -e "${GREEN}[INFO]${NC} $1"; }
log_warn() { echo -e "${YELLOW}[WARN]${NC} $1"; }
log_error() { echo -e "${RED}[ERROR]${NC} $1"; }

# Check if gcloud is installed
if ! command -v gcloud &> /dev/null; then
    log_error "gcloud CLI is not installed. Please install it first."
    exit 1
fi

# Set project
gcloud config set project "${PROJECT_ID}" --quiet

# Get VM external IP
VM_IP=$(gcloud compute instances describe "${VM_NAME}" --zone="${ZONE}" --format='get(networkInterfaces[0].accessConfigs[0].natIP)')
log_info "Deploying to ${VM_NAME} (${VM_IP})..."
log_info "Services: ${SERVICES}"

# Copy compose file and env to server
log_info "Uploading configuration files..."
gcloud compute scp --zone="${ZONE}" \
    "${SCRIPT_DIR}/compose.gcp.yaml" \
    "${VM_NAME}:${REMOTE_DIR}/"

# Copy .env if it exists
if [ -f "${SCRIPT_DIR}/.env.production" ]; then
    gcloud compute scp --zone="${ZONE}" \
        "${SCRIPT_DIR}/.env.production" \
        "${VM_NAME}:${REMOTE_DIR}/.env"
fi



# Deploy on server
log_info "Deploying containers..."
gcloud compute ssh "${VM_NAME}" --zone="${ZONE}" --command="
    cd ${REMOTE_DIR}

    # Authenticate Docker with Artifact Registry
    echo 'Authenticating with Artifact Registry...'
    gcloud auth configure-docker us-central1-docker.pkg.dev --quiet

    # Pull specified images
    echo 'Pulling images: ${SERVICES}...'
    docker compose -f compose.gcp.yaml pull ${SERVICES}

    # Start/restart specified containers
    echo 'Starting containers: ${SERVICES}...'
    docker compose -f compose.gcp.yaml up -d --remove-orphans ${SERVICES}

    # Clean up old images to save disk space
    echo 'Cleaning up old images...'
    docker system prune -f

    # Show status
    echo ''
    echo 'Container status:'
    docker compose -f compose.gcp.yaml ps
"



# Get the static IP
STATIC_IP=$(gcloud compute addresses describe "${VM_NAME}-ip" --region="us-central1" --format='get(address)' 2>/dev/null || echo "$VM_IP")

# Print summary
echo ""
echo "=========================================="
echo -e "${GREEN}Deployment Complete${NC}"
echo "=========================================="
echo ""
echo "Deployed services: ${SERVICES}"
echo ""
echo "Application URL: http://${STATIC_IP}"
echo "Backoffice URL:  http://${STATIC_IP}:8081"
echo ""
echo "To start Adminer (database UI):"
echo "  gcloud compute ssh ${VM_NAME} --zone=${ZONE} --command='cd ${REMOTE_DIR} && docker compose -f compose.gcp.yaml --profile tools up -d adminer'"
echo "  Then access: http://${STATIC_IP}:8082"
echo ""
echo "To view logs:"
echo "  gcloud compute ssh ${VM_NAME} --zone=${ZONE} --command='cd ${REMOTE_DIR} && docker compose -f compose.gcp.yaml logs -f'"
echo ""
