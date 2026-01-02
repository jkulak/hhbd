#!/bin/bash
#
# HHBD - Content Upload Script
# Compresses, uploads, and decompresses the content folder to the GCP VM
#
# Usage: ./05-upload-content.sh
#

set -euo pipefail

# Configuration
PROJECT_ID="hhbd-483111"
ZONE="us-central1-a"
VM_NAME="hhbd-server"
REMOTE_DIR="/opt/hhbd"
LOCAL_CONTENT_DIR="content"
ARCHIVE_NAME="content.tar.gz"

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

# Check if content directory exists
if [ ! -d "$LOCAL_CONTENT_DIR" ]; then
    log_error "The '$LOCAL_CONTENT_DIR' directory does not exist in the current location."
    exit 1
fi

# Set project
gcloud config set project "${PROJECT_ID}" --quiet

# Step 1: Count local files and directories
log_info "Counting local files and directories..."
LOCAL_FILE_COUNT=$(find "$LOCAL_CONTENT_DIR" -type f | wc -l)
LOCAL_DIR_COUNT=$(find "$LOCAL_CONTENT_DIR" -type d | wc -l)
log_info "Local content: ${LOCAL_FILE_COUNT} files, ${LOCAL_DIR_COUNT} directories"

# Step 2: Compress the content directory
log_info "Compressing '$LOCAL_CONTENT_DIR' directory..."
COPYFILE_DISABLE=1 tar --no-xattr -czf "$ARCHIVE_NAME" "$LOCAL_CONTENT_DIR"
log_info "Compression complete: $ARCHIVE_NAME"

# Step 3: Upload the archive to the server
log_info "Uploading '$ARCHIVE_NAME' to the server..."
gcloud compute scp --zone="${ZONE}" \
    "$ARCHIVE_NAME" \
    "${VM_NAME}:${REMOTE_DIR}/"

# Step 4: Decompress and verify on the server
log_info "Decompressing and verifying the archive on the server..."
gcloud compute ssh "${VM_NAME}" --zone="${ZONE}" --command="
    cd ${REMOTE_DIR}
    
    echo '[INFO] Decompressing content...'
    tar --pax-option=delete=SCHILY.* --pax-option=delete=LIBARCHIVE.* -xzf ${ARCHIVE_NAME}
    
    echo '[INFO] Verifying file and directory counts...'
    REMOTE_FILE_COUNT=\$(find content -type f | wc -l)
    REMOTE_DIR_COUNT=\$(find content -type d | wc -l)
    
    if [ \"\$REMOTE_FILE_COUNT\" -eq \"${LOCAL_FILE_COUNT}\" ] && [ \"\$REMOTE_DIR_COUNT\" -eq \"${LOCAL_DIR_COUNT}\" ]; then
        echo '[SUCCESS] File and directory counts match.'
        echo \"Remote content: \$REMOTE_FILE_COUNT files, \$REMOTE_DIR_COUNT directories\"
    else
        echo '[ERROR] Mismatch in file or directory counts!'
        echo \"Local:  ${LOCAL_FILE_COUNT} files, ${LOCAL_DIR_COUNT} directories\"
        echo \"Remote: \$REMOTE_FILE_COUNT files, \$REMOTE_DIR_COUNT directories\"
        exit 1
    fi
    
    echo '[INFO] Cleaning up the remote archive...'
    rm ${ARCHIVE_NAME}
"

# Step 5: Clean up the local archive
log_info "Cleaning up the local archive..."
rm "$ARCHIVE_NAME"

echo ""
echo "=========================================="
echo -e "${GREEN}Content Upload Complete${NC}"
echo "=========================================="
echo ""
