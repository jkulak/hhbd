#!/bin/bash
#
# HHBD - Load Test using mysqlslap
# Measures query performance under load
#
# Usage:
#   bash tools/load-test.sh [before|after]
#

STAGE="${1:-before}"
OUTPUT_FILE="load-test-${STAGE}.txt"
DB_CONTAINER="hhbd-db-1"
ROOT_PASSWORD="${DB_ROOT_PASSWORD:-root_password}"

echo "=== HHBD Load Test - $STAGE ($(date)) ===" > "$OUTPUT_FILE"
echo "Test: 10 concurrent connections, 100 iterations" >> "$OUTPUT_FILE"
echo "" >> "$OUTPUT_FILE"

# Check if container is running
if ! docker ps | grep -q "$DB_CONTAINER"; then
    echo "❌ Container $DB_CONTAINER not running"
    exit 1
fi

# Run mysqlslap with realistic queries
docker exec "$DB_CONTAINER" mysqlslap \
  --user=root \
  --password="$ROOT_PASSWORD" \
  --host=localhost \
  --concurrency=10 \
  --iterations=100 \
  --number-of-queries=300 \
  --query="SELECT * FROM albums WHERE status=999 LIMIT 50; SELECT * FROM artists WHERE status=999 LIMIT 50; SELECT * FROM songs LIMIT 100;" \
  --create-schema=hhbd \
  --verbose 2>&1 | tee -a "$OUTPUT_FILE"

echo "" >> "$OUTPUT_FILE"
echo "✅ Load test results saved to: $OUTPUT_FILE"
