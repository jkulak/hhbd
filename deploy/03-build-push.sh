#!/bin/bash
#
# HHBD - Build and Push Docker Images
# Run this script LOCALLY to build images and push to Artifact Registry
#
# Prerequisites:
#   - Docker installed locally
#   - gcloud CLI authenticated
#   - GCP infrastructure set up (01-setup-gcp.sh)
#
# Usage:
#   ./03-build-push.sh                    # Build all images
#   ./03-build-push.sh app                # Build only app image
#   ./03-build-push.sh nginx              # Build only nginx image
#   ./03-build-push.sh app nginx          # Build app and nginx images
#   ./03-build-push.sh --no-cache app     # Build with --no-cache
#

set -euo pipefail

# Configuration
PROJECT_ID="hhbd-483111"
REGION="us-central1"
REGISTRY_NAME="hhbd"
REGISTRY="${REGION}-docker.pkg.dev/${PROJECT_ID}/${REGISTRY_NAME}"

# Get script directory and project root
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"

# Parse arguments
NO_CACHE=""
BUILD_APP=false
BUILD_NGINX=false
SERVICES_SPECIFIED=false

while [[ $# -gt 0 ]]; do
    case $1 in
        --no-cache)
            NO_CACHE="--no-cache"
            shift
            ;;
        app)
            BUILD_APP=true
            SERVICES_SPECIFIED=true
            shift
            ;;
        nginx)
            BUILD_NGINX=true
            SERVICES_SPECIFIED=true
            shift
            ;;
        *)
            echo "Unknown option: $1"
            echo "Usage: $0 [--no-cache] [app] [nginx]"
            exit 1
            ;;
    esac
done

# If no services specified, build all
if [ "$SERVICES_SPECIFIED" = false ]; then
    BUILD_APP=true
    BUILD_NGINX=true
fi

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

log_info() { echo -e "${GREEN}[INFO]${NC} $1"; }
log_warn() { echo -e "${YELLOW}[WARN]${NC} $1"; }
log_error() { echo -e "${RED}[ERROR]${NC} $1"; }

# Check if docker is installed
if ! command -v docker &> /dev/null; then
    log_error "Docker is not installed. Please install it first."
    exit 1
fi

# Check if gcloud is installed
if ! command -v gcloud &> /dev/null; then
    log_error "gcloud CLI is not installed. Please install it first."
    exit 1
fi

# Authenticate with Artifact Registry
log_info "Authenticating with Artifact Registry..."
gcloud auth configure-docker "${REGION}-docker.pkg.dev" --quiet

# Change to project root
cd "$PROJECT_ROOT"

# Target platform for GCP (AMD64)
PLATFORM="linux/amd64"

# Track built images
BUILT_IMAGES=()

# Build and push PHP-FPM app image
if [ "$BUILD_APP" = true ]; then
    log_info "Building app image for ${PLATFORM}..."
    docker build ${NO_CACHE} \
        --platform "${PLATFORM}" \
        -t "${REGISTRY}/app:latest" \
        -f Dockerfile-php \
        .

    log_info "Pushing app image..."
    docker push "${REGISTRY}/app:latest"
    BUILT_IMAGES+=("${REGISTRY}/app:latest")
fi

# Build and push Nginx image
if [ "$BUILD_NGINX" = true ]; then
    log_info "Building nginx image for ${PLATFORM}..."
    docker build ${NO_CACHE} \
        --platform "${PLATFORM}" \
        -t "${REGISTRY}/nginx:latest" \
        -f Dockerfile-nginx \
        .

    log_info "Pushing nginx image..."
    docker push "${REGISTRY}/nginx:latest"
    BUILT_IMAGES+=("${REGISTRY}/nginx:latest")
fi

# Print summary
echo ""
echo "=========================================="
echo -e "${GREEN}Build and Push Complete${NC}"
echo "=========================================="
echo ""
echo "Images pushed to: ${REGISTRY}"
echo ""
for img in "${BUILT_IMAGES[@]}"; do
    echo "  - ${img}"
done
echo ""
echo "Next step: Run ./deploy/04-deploy.sh"
echo ""
