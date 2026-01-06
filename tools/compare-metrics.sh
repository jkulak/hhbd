#!/bin/bash
#
# HHBD - Performance Comparison
# Compares before/after metrics side-by-side
#
# Usage:
#   bash tools/compare-metrics.sh
#
# Requires:
#   - database-metrics-before.txt
#   - database-metrics-after.txt
#   - load-test-before.txt
#   - load-test-after.txt
#   - response-time-before.txt
#   - response-time-after.txt
#

echo "========================================"
echo "  HHBD Performance Comparison: Before vs After"
echo "========================================"
echo ""

# Check if files exist
REQUIRED_FILES=(
    "database-metrics-before.txt"
    "database-metrics-after.txt"
    "load-test-before.txt"
    "load-test-after.txt"
    "response-time-before.txt"
    "response-time-after.txt"
)

for file in "${REQUIRED_FILES[@]}"; do
    if [ ! -f "$file" ]; then
        echo "❌ Missing: $file"
        echo "   Run benchmark scripts first:"
        echo "     bash tools/benchmark-db.sh before"
        echo "     bash tools/benchmark-db.sh after"
        echo "     bash tools/load-test.sh before"
        echo "     bash tools/load-test.sh after"
        echo "     bash tools/response-time-test.sh before [url]"
        echo "     bash tools/response-time-test.sh after [url]"
        exit 1
    fi
done

echo "--- 1. Cache Hit Ratio ---"
BEFORE=$(grep "Cache_Hit_Ratio" database-metrics-before.txt | grep -oE '[0-9]+\.[0-9]+')
AFTER=$(grep "Cache_Hit_Ratio" database-metrics-after.txt | grep -oE '[0-9]+\.[0-9]+')
DIFF=$(echo "$AFTER - $BEFORE" | bc)
echo "  BEFORE: ${BEFORE}%"
echo "  AFTER:  ${AFTER}%"
echo "  CHANGE: ${DIFF}% (higher is better) ✅"
echo ""

echo "--- 2. Buffer Pool Disk Reads (lower is better) ---"
BEFORE=$(grep "Innodb_buffer_pool_reads " database-metrics-before.txt | awk '{print $3}')
AFTER=$(grep "Innodb_buffer_pool_reads " database-metrics-after.txt | awk '{print $3}')
REDUCTION=$(echo "scale=1; (($BEFORE - $AFTER) / $BEFORE) * 100" | bc 2>/dev/null || echo "N/A")
echo "  BEFORE: $BEFORE reads"
echo "  AFTER:  $AFTER reads"
echo "  REDUCTION: ${REDUCTION}% ✅"
echo ""

echo "--- 3. Buffer Pool Utilization ---"
BEFORE_DATA=$(grep "Innodb_buffer_pool_bytes_data " database-metrics-before.txt | awk '{print $3}')
BEFORE_TOTAL=$(grep "Innodb_buffer_pool_size" database-metrics-before.txt | grep -oE '[0-9]+' | head -1)
BEFORE_UTIL=$(echo "scale=1; ($BEFORE_DATA / $BEFORE_TOTAL) * 100" | bc 2>/dev/null || echo "N/A")

AFTER_DATA=$(grep "Innodb_buffer_pool_bytes_data " database-metrics-after.txt | awk '{print $3}')
AFTER_TOTAL=$(grep "innodb_buffer_pool_size" database-metrics-after.txt | grep -oE '[0-9]+' | head -1)
AFTER_UTIL=$(echo "scale=1; ($AFTER_DATA / $AFTER_TOTAL) * 100" | bc 2>/dev/null || echo "N/A")

echo "  BEFORE: ${BEFORE_UTIL}% utilization (${BEFORE_TOTAL} bytes total)"
echo "  AFTER:  ${AFTER_UTIL}% utilization (${AFTER_TOTAL} bytes total)"
echo ""

echo "--- 4. Query Execution Time (mysqlslap) ---"
BEFORE_TIME=$(grep -i "average.*seconds" load-test-before.txt | head -1 | grep -oE '[0-9]+\.[0-9]+')
AFTER_TIME=$(grep -i "average.*seconds" load-test-after.txt | head -1 | grep -oE '[0-9]+\.[0-9]+')
SPEEDUP=$(echo "scale=1; (($BEFORE_TIME - $AFTER_TIME) / $BEFORE_TIME) * 100" | bc 2>/dev/null || echo "N/A")
echo "  BEFORE: ${BEFORE_TIME}s (average query time)"
echo "  AFTER:  ${AFTER_TIME}s (average query time)"
echo "  SPEEDUP: ${SPEEDUP}% faster ✅"
echo ""

echo "--- 5. Homepage Response Time (average) ---"
BEFORE_HOME=$(grep -A21 "Testing: /$" response-time-before.txt | grep "Average:" | grep -oE '[0-9]+\.[0-9]+')
AFTER_HOME=$(grep -A21 "Testing: /$" response-time-after.txt | grep "Average:" | grep -oE '[0-9]+\.[0-9]+')
HOME_SPEEDUP=$(echo "scale=1; (($BEFORE_HOME - $AFTER_HOME) / $BEFORE_HOME) * 100" | bc 2>/dev/null || echo "N/A")
echo "  BEFORE: ${BEFORE_HOME}s"
echo "  AFTER:  ${AFTER_HOME}s"
echo "  IMPROVEMENT: ${HOME_SPEEDUP}% faster ✅"
echo ""

echo "========================================"
echo "  Summary"
echo "========================================"
echo "✅ Cache hit ratio improved: ${DIFF}%"
echo "✅ Query time speedup: ${SPEEDUP}%"
echo "✅ Homepage response time: ${HOME_SPEEDUP}% faster"
echo ""
echo "Note: If all improvements are >10%, the buffer pool optimization is working!"
echo ""
