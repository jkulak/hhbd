#!/bin/bash
#
# HHBD - Database Metrics Benchmark
# Captures buffer pool and performance metrics
#
# Usage:
#   bash tools/benchmark-db.sh [before|after]
#

STAGE="${1:-before}"
OUTPUT_FILE="database-metrics-${STAGE}.txt"
DB_CONTAINER="hhbd-db-1"
ROOT_PASSWORD="${DB_ROOT_PASSWORD:-root_password}"

echo "=== HHBD Database Metrics - $STAGE ($(date)) ===" > "$OUTPUT_FILE"
echo "" >> "$OUTPUT_FILE"

# Check if container is running
if ! docker ps | grep -q "$DB_CONTAINER"; then
    echo "❌ Container $DB_CONTAINER not running"
    exit 1
fi

# 1. Buffer Pool Configuration
echo "--- Buffer Pool Configuration ---" >> "$OUTPUT_FILE"
docker exec "$DB_CONTAINER" mysql -uroot -p"$ROOT_PASSWORD" -e "
  SELECT 
    VARIABLE_NAME, 
    VARIABLE_VALUE 
  FROM information_schema.GLOBAL_VARIABLES 
  WHERE VARIABLE_NAME IN (
    'innodb_buffer_pool_size',
    'innodb_old_blocks_time',
    'innodb_read_ahead_threshold'
  )
  ORDER BY VARIABLE_NAME;
" >> "$OUTPUT_FILE" 2>/dev/null

echo "" >> "$OUTPUT_FILE"

# 2. Current Buffer Pool Stats
echo "--- InnoDB Buffer Pool Statistics ---" >> "$OUTPUT_FILE"
docker exec "$DB_CONTAINER" mysql -uroot -p"$ROOT_PASSWORD" -e "
  SELECT 
    VARIABLE_NAME, 
    VARIABLE_VALUE 
  FROM information_schema.GLOBAL_STATUS 
  WHERE VARIABLE_NAME IN (
    'Innodb_buffer_pool_read_requests',
    'Innodb_buffer_pool_reads',
    'Innodb_buffer_pool_pages_data',
    'Innodb_buffer_pool_pages_free',
    'Innodb_buffer_pool_pages_total',
    'Innodb_buffer_pool_bytes_data',
    'Innodb_buffer_pool_bytes_free'
  )
  ORDER BY VARIABLE_NAME;
" >> "$OUTPUT_FILE" 2>/dev/null

echo "" >> "$OUTPUT_FILE"

# 3. Calculate cache hit ratio
echo "--- Cache Hit Ratio (%) ---" >> "$OUTPUT_FILE"
docker exec "$DB_CONTAINER" mysql -uroot -p"$ROOT_PASSWORD" -e "
  SELECT 
    ROUND(100 - (
      CAST((SELECT VARIABLE_VALUE FROM information_schema.GLOBAL_STATUS WHERE VARIABLE_NAME='Innodb_buffer_pool_reads') AS DECIMAL) / 
      CAST((SELECT VARIABLE_VALUE FROM information_schema.GLOBAL_STATUS WHERE VARIABLE_NAME='Innodb_buffer_pool_read_requests') AS DECIMAL) * 100
    ), 2) AS 'Cache_Hit_Ratio_Percent';
" >> "$OUTPUT_FILE" 2>/dev/null

echo "" >> "$OUTPUT_FILE"

# 4. Database size
echo "--- Database Size ---" >> "$OUTPUT_FILE"
docker exec "$DB_CONTAINER" mysql -uroot -p"$ROOT_PASSWORD" -e "
  SELECT 
    table_schema AS 'Database',
    ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS 'Size_MB'
  FROM information_schema.tables
  WHERE table_schema = 'hhbd'
  GROUP BY table_schema;
" >> "$OUTPUT_FILE" 2>/dev/null

echo "" >> "$OUTPUT_FILE"

# 5. Top tables by size
echo "--- Top 10 Largest Tables ---" >> "$OUTPUT_FILE"
docker exec "$DB_CONTAINER" mysql -uroot -p"$ROOT_PASSWORD" -e "
  SELECT 
    table_name AS 'Table',
    ROUND((data_length + index_length) / 1024 / 1024, 2) AS 'Size_MB',
    table_rows AS 'Rows'
  FROM information_schema.tables
  WHERE table_schema = 'hhbd'
  ORDER BY (data_length + index_length) DESC
  LIMIT 10;
" >> "$OUTPUT_FILE" 2>/dev/null

echo "" >> "$OUTPUT_FILE"

# 6. Query count
echo "--- Query Statistics (since startup) ---" >> "$OUTPUT_FILE"
docker exec "$DB_CONTAINER" mysql -uroot -p"$ROOT_PASSWORD" -e "
  SELECT 
    VARIABLE_NAME, 
    VARIABLE_VALUE 
  FROM information_schema.GLOBAL_STATUS 
  WHERE VARIABLE_NAME IN (
    'Questions',
    'Slow_queries',
    'Threads_connected'
  )
  ORDER BY VARIABLE_NAME;
" >> "$OUTPUT_FILE" 2>/dev/null

echo "" >> "$OUTPUT_FILE"
echo "✅ Metrics saved to: $OUTPUT_FILE"
cat "$OUTPUT_FILE"
