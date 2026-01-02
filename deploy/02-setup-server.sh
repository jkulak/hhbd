#!/bin/bash
#
# HHBD - Server Setup Script
# Run this script ON THE VM after first SSH
#
# Prerequisites:
#   - Ubuntu 22.04 LTS
#   - Run as root (sudo)
#
# Usage: sudo ./02-setup-server.sh
#

set -euo pipefail

# Configuration
PROJECT_ID="hhbd-483111"
REGION="us-central1"
REGISTRY_NAME="hhbd"
APP_DIR="/opt/hhbd"
SWAP_SIZE="4G"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

log_info() { echo -e "${GREEN}[INFO]${NC} $1"; }
log_warn() { echo -e "${YELLOW}[WARN]${NC} $1"; }
log_error() { echo -e "${RED}[ERROR]${NC} $1"; }

# Check if running as root
if [ "$EUID" -ne 0 ]; then
    log_error "Please run as root (sudo ./02-setup-server.sh)"
    exit 1
fi

# Update system packages
log_info "Updating system packages..."
apt-get update
apt-get upgrade -y

# Install required packages
log_info "Installing required packages..."
apt-get install -y \
    apt-transport-https \
    ca-certificates \
    curl \
    gnupg \
    lsb-release \
    rsync

# Install Docker
log_info "Installing Docker..."
if command -v docker &> /dev/null; then
    log_warn "Docker is already installed"
else
    # Add Docker's official GPG key
    install -m 0755 -d /etc/apt/keyrings
    curl -fsSL https://download.docker.com/linux/ubuntu/gpg -o /etc/apt/keyrings/docker.asc
    chmod a+r /etc/apt/keyrings/docker.asc

    # Add the repository to Apt sources
    echo \
      "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.asc] https://download.docker.com/linux/ubuntu \
      $(. /etc/os-release && echo "$VERSION_CODENAME") stable" | \
      tee /etc/apt/sources.list.d/docker.list > /dev/null

    apt-get update

    # Install Docker with retry/fix-missing in case of repository issues
    apt-get install -y --fix-missing docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin || {
        log_warn "Retrying Docker installation..."
        apt-get update --fix-missing
        apt-get install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin
    }

    log_info "Docker installed successfully"
fi

# Enable Docker to start on boot
systemctl enable docker
systemctl start docker

# Add current user to docker group (for non-root usage)
SUDO_USER_NAME="${SUDO_USER:-$(whoami)}"
if [ "$SUDO_USER_NAME" != "root" ]; then
    usermod -aG docker "$SUDO_USER_NAME"
    log_info "Added user '$SUDO_USER_NAME' to docker group"
fi

# Create swap file
log_info "Setting up swap file..."
if [ -f /swapfile ]; then
    log_warn "Swap file already exists"
else
    fallocate -l "${SWAP_SIZE}" /swapfile
    chmod 600 /swapfile
    mkswap /swapfile
    swapon /swapfile

    # Make swap permanent
    echo '/swapfile none swap sw 0 0' >> /etc/fstab

    # Optimize swap settings for low RAM
    echo 'vm.swappiness=10' >> /etc/sysctl.conf
    echo 'vm.vfs_cache_pressure=50' >> /etc/sysctl.conf
    sysctl -p

    log_info "Swap file created (${SWAP_SIZE})"
fi

# Create application directory
log_info "Creating application directory..."
mkdir -p "${APP_DIR}"
mkdir -p "${APP_DIR}/content"
mkdir -p "${APP_DIR}/database"
chown -R "$SUDO_USER_NAME:$SUDO_USER_NAME" "${APP_DIR}"

# Configure Docker to authenticate with Artifact Registry
log_info "Configuring Artifact Registry authentication..."
gcloud auth configure-docker "${REGION}-docker.pkg.dev" --quiet

# Create a simple deployment helper script
cat > "${APP_DIR}/quick-deploy.sh" << 'EOF'
#!/bin/bash
# Quick deploy helper - pulls latest images and restarts
cd /opt/hhbd
docker compose -f compose.gcp.yaml pull
docker compose -f compose.gcp.yaml up -d --remove-orphans
docker system prune -f
echo "Deployment complete!"
EOF
chmod +x "${APP_DIR}/quick-deploy.sh"

# Print summary
echo ""
echo "=========================================="
echo -e "${GREEN}Server Setup Complete${NC}"
echo "=========================================="
echo ""
echo "Docker version: $(docker --version)"
echo "Compose version: $(docker compose version)"
echo "Swap: $(swapon --show)"
echo ""
echo "Application directory: ${APP_DIR}"
echo ""
echo "Next steps:"
echo "  1. From your LOCAL machine, run:"
echo "     ./deploy/03-build-push.sh"
echo ""
echo "  2. Then deploy with:"
echo "     ./deploy/04-deploy.sh"
echo ""
echo "  3. Or quick deploy from the server:"
echo "     ${APP_DIR}/quick-deploy.sh"
echo ""
log_warn "Please log out and back in for docker group changes to take effect."
echo ""
