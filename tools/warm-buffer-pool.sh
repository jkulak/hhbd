#!/bin/bash
#
# HHBD - Warm Buffer Pool
# Loads all database tables into InnoDB buffer pool on startup
#
# Run this after MariaDB starts to pre-warm the cache
# With small database, all data will stay in memory indefinitely
#
# Usage:
#   bash tools/warm-buffer-pool.sh
#

DB_CONTAINER="hhbd-db-1"
ROOT_PASSWORD="${DB_ROOT_PASSWORD:-root_password}"

echo "🔥 Warming up InnoDB buffer pool..."
echo ""

# Check if container is running
if ! docker ps | grep -q "$DB_CONTAINER"; then
    echo "❌ Container $DB_CONTAINER not running"
    exit 1
fi

# Wait for database to be ready
echo "⏳ Waiting for database..."
for i in {1..30}; do
    if docker exec "$DB_CONTAINER" mysql -uroot -p"$ROOT_PASSWORD" -e "SELECT 1" &>/dev/null; then
        break
    fi
    sleep 1
done

echo "📊 Running SELECT COUNT on all tables..."
echo ""

# Get list of all tables and run COUNT(*) on each
docker exec "$DB_CONTAINER" mysql -uroot -p"$ROOT_PASSWORD" hhbd -e "
  SELECT table_name FROM information_schema.tables WHERE table_schema='hhbd' AND table_type='BASE TABLE';
" | tail -n +2 | while read table; do
    echo "  Loading: $table..."
    docker exec "$DB_CONTAINER" mysql -uroot -p"$ROOT_PASSWORD" hhbd -e "SELECT COUNT(*) FROM $table;" &>/dev/null
done

echo ""
echo "✅ Buffer pool warmed up!"
echo ""

# Show final buffer pool stats
echo "📈 Buffer Pool Status:"
docker exec "$DB_CONTAINER" mysql -uroot -p"$ROOT_PASSWORD" -e "
  SELECT 
    'Buffer Pool Size (MB)' AS Metric,
    ROUND(@@innodb_buffer_pool_size / 1024 / 1024, 0) AS Value
  UNION ALL
  SELECT 
    'Pages Used (%)' AS Metric,
    ROUND((VARIABLE_VALUE / @@innodb_buffer_pool_size) * 100, 1) AS Value
  FROM information_schema.GLOBAL_STATUS
  WHERE VARIABLE_NAME = 'Innodb_buffer_pool_bytes_data'
  UNION ALL
  SELECT 
    'Cache Hit Ratio (%)' AS Metric,
    ROUND(100 - (
      CAST(VARIABLE_VALUE AS DECIMAL) / 
      (SELECT VARIABLE_VALUE FROM information_schema.GLOBAL_STATUS WHERE VARIABLE_NAME='Innodb_buffer_pool_read_requests') * 100
    ), 2) AS Value
  FROM information_schema.GLOBAL_STATUS
  WHERE VARIABLE_NAME = 'Innodb_buffer_pool_reads'
;" 2>/dev/null

echo ""
echo "💾 All data is now in memory and will persist until restart!"
