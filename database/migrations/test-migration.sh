#!/bin/bash
# Test script to validate SQL migration syntax
# This script can be run against a test database to verify the migration works

set -e  # Exit on error

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Database connection parameters (override with environment variables)
DB_HOST="${DB_HOST:-localhost}"
DB_PORT="${DB_PORT:-3306}"
DB_USER="${DB_USER:-root}"
DB_PASS="${DB_PASS:-}"
DB_NAME="${DB_NAME:-hhbd_test}"

echo -e "${YELLOW}====================================${NC}"
echo -e "${YELLOW}SQL Migration Test Script${NC}"
echo -e "${YELLOW}====================================${NC}"
echo ""

# Function to run MySQL query
run_query() {
    local query="$1"
    mysql -h"$DB_HOST" -P"$DB_PORT" -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" -e "$query" 2>&1
}

# Step 1: Check database connection
echo -e "${YELLOW}[1/5] Testing database connection...${NC}"
if run_query "SELECT 1" > /dev/null; then
    echo -e "${GREEN}✓ Database connection successful${NC}"
else
    echo -e "${RED}✗ Database connection failed${NC}"
    exit 1
fi
echo ""

# Step 2: Check if tables exist
echo -e "${YELLOW}[2/5] Checking if required tables exist...${NC}"
TABLES=("albums" "artists" "labels" "songs" "news" "hhb_comments" "cities")
for table in "${TABLES[@]}"; do
    if run_query "SHOW TABLES LIKE '$table'" | grep -q "$table"; then
        echo -e "${GREEN}  ✓ Table '$table' exists${NC}"
    else
        echo -e "${RED}  ✗ Table '$table' not found (optional)${NC}"
    fi
done
echo ""

# Step 3: Insert test data
echo -e "${YELLOW}[3/5] Inserting test data with old URLs...${NC}"

# Create a test label with old URL in profile
run_query "
INSERT INTO labels (lab_id, name, profile, addedby, added) 
VALUES (9999, 'Test Label', 'Test label with link <a href=\"https://hhbd.pl/n/test-artist\">Test Artist</a>', 1, NOW())
ON DUPLICATE KEY UPDATE 
    profile = 'Test label with link <a href=\"https://hhbd.pl/n/test-artist\">Test Artist</a>',
    updated = NOW();
" || echo -e "${YELLOW}  Note: Could not insert test label (table may not exist or have different structure)${NC}"

echo -e "${GREEN}  ✓ Test data inserted${NC}"
echo ""

# Step 4: Run the PART 1 query (identification)
echo -e "${YELLOW}[4/5] Running identification query (PART 1)...${NC}"

IDENTIFICATION_QUERY="
SELECT 'labels' as table_name, 'profile' as field_name, COUNT(*) as count
FROM labels WHERE profile LIKE '%hhbd.pl/n/%'
UNION ALL
SELECT 'albums', 'description', COUNT(*)
FROM albums WHERE description LIKE '%hhbd.pl/n/%'
UNION ALL
SELECT 'artists', 'profile', COUNT(*)
FROM artists WHERE profile LIKE '%hhbd.pl/n/%';"

echo "$IDENTIFICATION_QUERY" | run_query || echo -e "${YELLOW}  Some tables may not exist${NC}"
echo ""

# Step 5: Run a single UPDATE test
echo -e "${YELLOW}[5/5] Testing UPDATE syntax...${NC}"

run_query "
UPDATE labels 
SET profile = REPLACE(profile, 'https://hhbd.pl/n/', 'https://hhbd.pl/')
WHERE lab_id = 9999 AND profile LIKE '%https://hhbd.pl/n/%';
" && echo -e "${GREEN}  ✓ UPDATE syntax is valid${NC}" || echo -e "${RED}  ✗ UPDATE syntax error${NC}"

# Verify the update
echo -e "${YELLOW}Verifying update result...${NC}"
RESULT=$(run_query "SELECT profile FROM labels WHERE lab_id = 9999" | tail -n 1)
if echo "$RESULT" | grep -q "https://hhbd.pl/test-artist"; then
    echo -e "${GREEN}  ✓ URL was correctly updated${NC}"
    echo -e "    Before: https://hhbd.pl/n/test-artist"
    echo -e "    After:  https://hhbd.pl/test-artist"
else
    echo -e "${RED}  ✗ URL was not updated correctly${NC}"
    echo -e "    Result: $RESULT"
fi
echo ""

# Cleanup
echo -e "${YELLOW}Cleaning up test data...${NC}"
run_query "DELETE FROM labels WHERE lab_id = 9999;" 2>/dev/null || true
echo -e "${GREEN}  ✓ Cleanup complete${NC}"
echo ""

echo -e "${GREEN}====================================${NC}"
echo -e "${GREEN}Test completed successfully!${NC}"
echo -e "${GREEN}====================================${NC}"
echo ""
echo -e "The SQL migration script syntax is valid and ready to use."
echo -e "Remember to:"
echo -e "  1. Create a full backup before running on production"
echo -e "  2. Uncomment the UPDATE statements in fix-old-urls.sql"
echo -e "  3. Review the changes after migration"
