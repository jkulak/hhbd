#!/bin/bash
#
# HHBD - Google Cloud Infrastructure Setup
# Run this script ONCE to provision the GCP environment
#
# Prerequisites:
#   - gcloud CLI installed and authenticated
#   - Billing enabled on the project
#
# Usage: ./01-setup-gcp.sh
#

set -euo pipefail

# Configuration
PROJECT_ID="hhbd-483111"
REGION="us-central1"
ZONE="us-central1-a"
VM_NAME="hhbd-server"
MACHINE_TYPE="e2-micro"
DISK_SIZE="30"
IMAGE_FAMILY="ubuntu-2204-lts"
IMAGE_PROJECT="ubuntu-os-cloud"
REGISTRY_NAME="hhbd"

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

# Set the project
log_info "Setting project to ${PROJECT_ID}..."
gcloud config set project "${PROJECT_ID}"

# Enable required APIs
log_info "Enabling required APIs..."
gcloud services enable compute.googleapis.com
gcloud services enable artifactregistry.googleapis.com

# Create Artifact Registry repository
log_info "Creating Artifact Registry repository..."
if gcloud artifacts repositories describe "${REGISTRY_NAME}" --location="${REGION}" &> /dev/null; then
    log_warn "Artifact Registry repository '${REGISTRY_NAME}' already exists"
else
    gcloud artifacts repositories create "${REGISTRY_NAME}" \
        --repository-format=docker \
        --location="${REGION}" \
        --description="HHBD Docker images"
    log_info "Artifact Registry repository created"
fi

# Reserve a static external IP
log_info "Reserving static external IP..."
if gcloud compute addresses describe "${VM_NAME}-ip" --region="${REGION}" &> /dev/null; then
    log_warn "Static IP '${VM_NAME}-ip' already exists"
else
    gcloud compute addresses create "${VM_NAME}-ip" \
        --region="${REGION}" \
        --network-tier=STANDARD
    log_info "Static IP reserved"
fi

# Get the reserved IP address
STATIC_IP=$(gcloud compute addresses describe "${VM_NAME}-ip" --region="${REGION}" --format='get(address)')
log_info "Static IP: ${STATIC_IP}"

# Create firewall rules
log_info "Creating firewall rules..."

# HTTP rule
if gcloud compute firewall-rules describe allow-http &> /dev/null; then
    log_warn "Firewall rule 'allow-http' already exists"
else
    gcloud compute firewall-rules create allow-http \
        --allow=tcp:80 \
        --target-tags=http-server \
        --description="Allow HTTP traffic"
fi

# HTTPS rule
if gcloud compute firewall-rules describe allow-https &> /dev/null; then
    log_warn "Firewall rule 'allow-https' already exists"
else
    gcloud compute firewall-rules create allow-https \
        --allow=tcp:443 \
        --target-tags=https-server \
        --description="Allow HTTPS traffic"
fi

# Backoffice rule (port 8081)
if gcloud compute firewall-rules describe allow-backoffice &> /dev/null; then
    log_warn "Firewall rule 'allow-backoffice' already exists"
else
    gcloud compute firewall-rules create allow-backoffice \
        --allow=tcp:8081 \
        --target-tags=backoffice \
        --description="Allow backoffice traffic on port 8081"
fi

# Create the VM instance
log_info "Creating VM instance..."
if gcloud compute instances describe "${VM_NAME}" --zone="${ZONE}" &> /dev/null; then
    log_warn "VM instance '${VM_NAME}' already exists"
else
    gcloud compute instances create "${VM_NAME}" \
        --zone="${ZONE}" \
        --machine-type="${MACHINE_TYPE}" \
        --image-family="${IMAGE_FAMILY}" \
        --image-project="${IMAGE_PROJECT}" \
        --boot-disk-size="${DISK_SIZE}GB" \
        --boot-disk-type=pd-standard \
        --address="${STATIC_IP}" \
        --network-tier=STANDARD \
        --tags=http-server,https-server,backoffice \
        --metadata=enable-oslogin=TRUE \
        --scopes=https://www.googleapis.com/auth/devstorage.read_only,https://www.googleapis.com/auth/logging.write,https://www.googleapis.com/auth/monitoring.write,https://www.googleapis.com/auth/servicecontrol,https://www.googleapis.com/auth/service.management.readonly,https://www.googleapis.com/auth/trace.append,https://www.googleapis.com/auth/cloud-platform
    log_info "VM instance created"
fi

# Print summary
echo ""
echo "=========================================="
echo -e "${GREEN}GCP Infrastructure Setup Complete${NC}"
echo "=========================================="
echo ""
echo "Project ID:     ${PROJECT_ID}"
echo "VM Name:        ${VM_NAME}"
echo "Zone:           ${ZONE}"
echo "Machine Type:   ${MACHINE_TYPE}"
echo "Static IP:      ${STATIC_IP}"
echo "Registry:       ${REGION}-docker.pkg.dev/${PROJECT_ID}/${REGISTRY_NAME}"
echo ""
echo "Next steps:"
echo "  1. SSH into the VM:"
echo "     gcloud compute ssh ${VM_NAME} --zone=${ZONE}"
echo ""
echo "  2. Copy and run the server setup script:"
echo "     scp deploy/02-setup-server.sh ${VM_NAME}:~/"
echo "     # Or use: gcloud compute scp deploy/02-setup-server.sh ${VM_NAME}:~/ --zone=${ZONE}"
echo ""
echo "  3. On the VM, run:"
echo "     chmod +x 02-setup-server.sh && sudo ./02-setup-server.sh"
echo ""
echo "  4. Point your domain DNS A record to: ${STATIC_IP}"
echo ""
