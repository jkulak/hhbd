#!/bin/bash
#
# HHBD - Response Time Benchmark
# Measures end-to-end page load times
#
# Usage:
#   bash tools/response-time-test.sh [before|after] [base_url]
#
# Examples:
#   bash tools/response-time-test.sh before http://localhost:8080
#   bash tools/response-time-test.sh after https://hhbd.pl
#

STAGE="${1:-before}"
BASE_URL="${2:-http://localhost:8080}"
OUTPUT_FILE="response-time-${STAGE}.txt"

echo "=== HHBD Response Time Test - $STAGE ($(date)) ===" > "$OUTPUT_FILE"
echo "Base URL: $BASE_URL" >> "$OUTPUT_FILE"
echo "Runs per page: 20" >> "$OUTPUT_FILE"
echo "" >> "$OUTPUT_FILE"

# Array of pages to test
declare -a PAGES=("/" "/artists.html" "/albums.html" "/top10.html" "/search.html")

# Test each page
for page in "${PAGES[@]}"; do
    echo "Testing: $page" | tee -a "$OUTPUT_FILE"
    
    # Array to store times
    declare -a TIMES
    
    # Run 20 times
    for i in {1..20}; do
        # Measure time in seconds with milliseconds
        TIME=$(curl -s -w "%{time_total}" -o /dev/null "$BASE_URL$page" 2>/dev/null)
        TIMES+=("$TIME")
        echo "  Run $i: ${TIME}s" >> "$OUTPUT_FILE"
    done
    
    # Calculate average
    TOTAL=0
    for time in "${TIMES[@]}"; do
        TOTAL=$(echo "$TOTAL + $time" | bc)
    done
    AVG=$(echo "scale=3; $TOTAL / 20" | bc)
    
    echo "  Average: ${AVG}s" | tee -a "$OUTPUT_FILE"
    echo "" | tee -a "$OUTPUT_FILE"
done

echo "✅ Response time test saved to: $OUTPUT_FILE"
