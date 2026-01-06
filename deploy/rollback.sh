#!/bin/bash
#
# HHBD - Rollback to Last-Known-Good Deployment
# 
# Rolls back the production deployment to the last confirmed working version
# by redeploying the prod-lkg tagged images.
#
# Prerequisites:
#   - gcloud CLI authenticated
#   - SSH access to the VM
#   - prod-lkg tags exist in Artifact Registry
#
# Usage:
#   ./rollback.sh                   # Rollback app and nginx
#   ./rollback.sh app               # Rollback only app
#   ./rollback.sh nginx             # Rollback only nginx
#

set -euo pipefail

# Configuration
PROJECT_ID="hhbd-483111"
ZONE="us-central1-a"
VM_NAME="hhbd-server"
REMOTE_DIR="/opt/hhbd"
REGION="us-central1"
REGISTRY_NAME="hhbd"

# Get script directory
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

# Parse arguments
ROLLBACK_APP=false
ROLLBACK_NGINX=false
SERVICES_SPECIFIED=false

while [[ $# -gt 0 ]]; do
    case $1 in
        app)
            ROLLBACK_APP=true
            SERVICES_SPECIFIED=true
            shift
            ;;
        nginx)
            ROLLBACK_NGINX=true
            SERVICES_SPECIFIED=true
            shift
            ;;
        *)
            echo "Unknown option: $1"
            echo "Usage: $0 [app] [nginx]"
            exit 1
            ;;
    esac
done

# If no services specified, rollback all
if [ "$SERVICES_SPECIFIED" = false ]; then
    ROLLBACK_APP=true
    ROLLBACK_NGINX=true
fi

# Build list of services to rollback
SERVICES=""
if [ "$ROLLBACK_APP" = true ]; then
    SERVICES="${SERVICES} app"
fi
if [ "$ROLLBACK_NGINX" = true ]; then
    SERVICES="${SERVICES} nginx"
fi
SERVICES=$(echo "$SERVICES" | xargs)  # Trim whitespace

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

log_info() { echo -e "${GREEN}[INFO]${NC} $1"; }
log_warn() { echo -e "${YELLOW}[WARN]${NC} $1"; }
log_error() { echo -e "${RED}[ERROR]${NC} $1"; }
log_action() { echo -e "${BLUE}[ACTION]${NC} $1"; }

# Check if gcloud is installed
if ! command -v gcloud &> /dev/null; then
    log_error "gcloud CLI is not installed. Please install it first."
    exit 1
fi

# Verify we're rolling back to known-good versions
log_warn "⚠️  This will redeploy to the last-known-good (prod-lkg) images"
log_warn "Services: ${SERVICES}"
echo ""
log_action "Confirm rollback? (type 'yes' to proceed): "
read -r CONFIRM

if [ "$CONFIRM" != "yes" ]; then
    log_error "Rollback cancelled."
    exit 0
fi

# Set project
gcloud config set project "${PROJECT_ID}" --quiet

# Check if prod-lkg tags exist in Artifact Registry
log_info "Verifying prod-lkg tags exist in Artifact Registry..."

REGISTRY="${REGION}-docker.pkg.dev/${PROJECT_ID}/${REGISTRY_NAME}"

check_tag_exists() {
    local image_name=$1
    local tag=$2
    
    # Try to get image manifest for the prod-lkg tag
    if gcloud container images describe "${REGISTRY}/${image_name}:${tag}" --quiet > /dev/null 2>&1; then
        return 0
    else
        return 1
    fi
}

MISSING_TAGS=()

if [ "$ROLLBACK_APP" = true ]; then
    if ! check_tag_exists "app" "prod-lkg"; then
        MISSING_TAGS+=("app:prod-lkg")
    fi
fi

if [ "$ROLLBACK_NGINX" = true ]; then
    if ! check_tag_exists "nginx" "prod-lkg"; then
        MISSING_TAGS+=("nginx:prod-lkg")
    fi
fi

if [ ${#MISSING_TAGS[@]} -gt 0 ]; then
    log_error "Cannot rollback: prod-lkg tags do not exist in Artifact Registry"
    log_error "Missing tags: ${MISSING_TAGS[*]}"
    echo ""
    log_warn "This typically happens on the first production deployment."
    log_warn "The prod-lkg tags are created after the first successful deployment"
    log_warn "when smoke tests pass."
    echo ""
    log_info "To establish prod-lkg baseline:"
    log_info "  1. Ensure you have a working deployment with 'latest' tags"
    log_info "  2. Run smoke tests to verify the deployment"
    log_info "  3. Manually tag the working images as prod-lkg:"
    echo ""
    for tag in "${MISSING_TAGS[@]}"; do
        image_name="${tag%%:*}"
        echo "     gcloud container images add-tag --quiet \\"
        echo "       ${REGISTRY}/${image_name}:latest \\"
        echo "       ${REGISTRY}/${tag}"
        echo ""
    done
    log_info "  Or wait for the CI/CD pipeline to automatically create prod-lkg tags"
    log_info "  after a successful deployment with passing smoke tests."
    exit 1
fi

log_info "✓ All required prod-lkg tags found"

# Get VM external IP
VM_IP=$(gcloud compute instances describe "${VM_NAME}" --zone="${ZONE}" --format='get(networkInterfaces[0].accessConfigs[0].natIP)')
log_info "Rolling back on ${VM_NAME} (${VM_IP})..."
log_info "Services: ${SERVICES}"

# Execute rollback on server
log_action "Redeploying prod-lkg images..."
gcloud compute ssh "${VM_NAME}" --zone="${ZONE}" --command="
    cd ${REMOTE_DIR}

    # Authenticate Docker with Artifact Registry
    echo 'Authenticating with Artifact Registry...'
    gcloud auth configure-docker us-central1-docker.pkg.dev --quiet

    # Set environment variables for docker compose (use prod-lkg tag)
    export APP_TAG=prod-lkg
    export NGINX_TAG=prod-lkg

    # Pull last-known-good images
    echo 'Pulling last-known-good images...'
    docker compose -f compose.gcp.yaml pull ${SERVICES}

    # Restart specified containers with prod-lkg images
    echo 'Restarting containers with prod-lkg images...'
    docker compose -f compose.gcp.yaml up -d --remove-orphans ${SERVICES}

    # Show status
    echo ''
    echo 'Container status:'
    docker compose -f compose.gcp.yaml ps
"

# Print summary
echo ""
echo "=========================================="
echo -e "${GREEN}Rollback Complete${NC}"
echo "=========================================="
echo ""
echo "Rolled back services: ${SERVICES}"
echo "Image tags used: prod-lkg"
echo ""
log_info "To verify the rollback:"
echo "  gcloud compute ssh ${VM_NAME} --zone=${ZONE} --command='cd ${REMOTE_DIR} && docker compose -f compose.gcp.yaml ps'"
echo ""
log_info "To view logs:"
echo "  gcloud compute ssh ${VM_NAME} --zone=${ZONE} --command='cd ${REMOTE_DIR} && docker compose -f compose.gcp.yaml logs -f'"
echo ""
