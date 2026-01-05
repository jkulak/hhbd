#!/bin/bash
#
# HHBD Smoke Tests
# Verifies that key pages are accessible and display database content
#
# Usage:
#   ./tests/smoke-test.sh              # Test localhost:8080
#   ./tests/smoke-test.sh http://example.com  # Test custom URL
#

BASE_URL="${1:-http://localhost:8080}"
FAILED=0
PASSED=0
ERRORS=()  # Array to collect error messages

# Detect if we need to add Host header (for docker service name connections)
CURL_OPTS=""
if [[ "$BASE_URL" == *"nginx"* ]] || [[ "$BASE_URL" == *"172.18"* ]]; then
    CURL_OPTS="-H Host:localhost"
fi

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo ""
echo "========================================"
echo "  HHBD Smoke Tests"
echo "  Base URL: $BASE_URL"
# echo "  Application Environment: $(php -r 'echo getenv("APPLICATION_ENV") ?: "production (default)"')"
echo "========================================"
echo ""

# Test a page for HTTP 200 and expected content
test_page() {
    local name="$1"
    local path="$2"
    local expected="$3"
    local url="${BASE_URL}${path}"

    # Get HTTP status code
    local status
    status=$(curl -s -o /dev/null -w "%{http_code}" --max-time 10 $CURL_OPTS "$url" 2>/dev/null || echo "000")

    # Get content if status is 200
    if [[ "$status" == "200" ]]; then
        local content
        content=$(curl -s --max-time 10 $CURL_OPTS "$url" 2>/dev/null)

        if echo "$content" | grep -qi "$expected"; then
            echo -e "${GREEN}✓${NC} $name"
            ((PASSED++))
            return 0
        else
            local error="$name - Content missing: '$expected'"
            echo -e "${RED}✗${NC} $error"
            ERRORS+=("$error")
            ((FAILED++))
            return 1
        fi
    else
        local error="$name - HTTP $status (expected 200)"
        echo -e "${RED}✗${NC} $error"
        ERRORS+=("$error")
        ((FAILED++))
        return 1
    fi
}

# Test that a page returns HTTP 200 (no content check)
test_page_200() {
    local name="$1"
    local path="$2"
    local url="${BASE_URL}${path}"

    local status
    status=$(curl -s -o /dev/null -w "%{http_code}" --max-time 10 $CURL_OPTS "$url" 2>/dev/null || echo "000")

    if [[ "$status" == "200" ]]; then
        echo -e "${GREEN}✓${NC} $name"
        ((PASSED++))
        return 0
    else
        local error="$name - HTTP $status (expected 200)"
        echo -e "${RED}✗${NC} $error"
        ERRORS+=("$error")
        ((FAILED++))
        return 1
    fi
}

# Wait for service to be ready
wait_for_service() {
    local max_attempts=30
    local attempt=1

    echo -e "${YELLOW}Waiting for service to be ready...${NC}"

    while [[ $attempt -le $max_attempts ]]; do
        status=$(curl -s -o /dev/null -w "%{http_code}" --max-time 5 $CURL_OPTS "$BASE_URL" 2>/dev/null || echo "000")
        if [[ "$status" == "200" ]]; then
            echo -e "${GREEN}Service is ready!${NC}"
            echo ""
            return 0
        fi
        echo "  Attempt $attempt/$max_attempts..."
        sleep 2
        ((attempt++))
    done

    echo -e "${RED}Service not ready after $max_attempts attempts${NC}"
    exit 1
}

# Test a page for HTTP 200 and multiple expected strings
test_page_multi() {
    local name="$1"
    local path="$2"
    shift 2
    local expected=("$@")
    local url="${BASE_URL}${path}"

    # Get HTTP status code
    local status
    status=$(curl -s -o /dev/null -w "%{http_code}" --max-time 10 $CURL_OPTS "$url" 2>/dev/null || echo "000")

    # Get content if status is 200
    if [[ "$status" == "200" ]]; then
        local content
        content=$(curl -s --max-time 10 $CURL_OPTS "$url" 2>/dev/null)

        for exp in "${expected[@]}"; do
            if ! echo "$content" | grep -qi "$exp"; then
                local error="$name - Content missing: '$exp'"
                echo -e "${RED}✗${NC} $error"
                ERRORS+=("$error")
                ((FAILED++))
                return 1
            fi
        done
        echo -e "${GREEN}✓${NC} $name"
        ((PASSED++))
        return 0
    else
        local error="$name - HTTP $status (expected 200)"
        echo -e "${RED}✗${NC} $error"
        ERRORS+=("$error")
        ((FAILED++))
        return 1
    fi
}

# Test that a page contains a minimum number of elements matching a pattern
test_element_count() {
    local name="$1"
    local path="$2"
    local pattern="$3"
    local min_count="$4"
    local url="${BASE_URL}${path}"

    local status
    status=$(curl -s -o /dev/null -w "%{http_code}" --max-time 10 $CURL_OPTS "$url" 2>/dev/null || echo "000")

    if [[ "$status" == "200" ]]; then
        local content
        content=$(curl -s --max-time 10 $CURL_OPTS "$url" 2>/dev/null)
        
        local count
        count=$(echo "$content" | grep -o "$pattern" | wc -l)

        if [[ "$count" -ge "$min_count" ]]; then
             echo -e "${GREEN}✓${NC} $name (Found $count elements, expected >= $min_count)"
             ((PASSED++))
             return 0
        else
             local error="$name - Found $count '$pattern', expected >= $min_count"
             echo -e "${RED}✗${NC} $error"
             ERRORS+=("$error")
             ((FAILED++))
             return 1
        fi
    else
        local error="$name - HTTP $status (expected 200)"
        echo -e "${RED}✗${NC} $error"
        ERRORS+=("$error")
        ((FAILED++))
        return 1
    fi
}

# Test that a page returns HTTP 301 redirect
test_redirect_301() {
    local name="$1"
    local path="$2"
    local expected_location="$3"
    local url="${BASE_URL}${path}"

    local status
    status=$(curl -s -o /dev/null -w "%{http_code}" --max-time 10 $CURL_OPTS "$url" 2>/dev/null || echo "000")

    if [[ "$status" == "301" ]]; then
        # Check if redirect location matches expected (if provided)
        if [[ -n "$expected_location" ]]; then
            local location
            location=$(curl -s -I --max-time 10 $CURL_OPTS "$url" 2>/dev/null | grep -i "^location:" | cut -d' ' -f2 | tr -d '\r')
            
            if [[ "$location" == *"$expected_location"* ]]; then
                echo -e "${GREEN}✓${NC} $name (redirects to $expected_location)"
                ((PASSED++))
                return 0
            else
                local error="$name - Redirects to '$location', expected '$expected_location'"
                echo -e "${RED}✗${NC} $error"
                ERRORS+=("$error")
                ((FAILED++))
                return 1
            fi
        else
            echo -e "${GREEN}✓${NC} $name"
            ((PASSED++))
            return 0
        fi
    else
        local error="$name - HTTP $status (expected 301)"
        echo -e "${RED}✗${NC} $error"
        ERRORS+=("$error")
        ((FAILED++))
        return 1
    fi
}

# Test that a page contains a canonical meta tag
test_canonical_tag() {
    local name="$1"
    local path="$2"
    local expected_canonical="$3"
    local url="${BASE_URL}${path}"

    local status
    status=$(curl -s -o /dev/null -w "%{http_code}" --max-time 10 $CURL_OPTS "$url" 2>/dev/null || echo "000")

    if [[ "$status" == "200" ]]; then
        local content
        content=$(curl -s --max-time 10 $CURL_OPTS "$url" 2>/dev/null)
        
        if echo "$content" | grep -qi '<link rel="canonical"'; then
            # Optionally check if canonical URL matches expected
            if [[ -n "$expected_canonical" ]]; then
                if echo "$content" | grep -qi "href=\"[^\"]*$expected_canonical\""; then
                    echo -e "${GREEN}✓${NC} $name"
                    ((PASSED++))
                    return 0
                else
                    local error="$name - Canonical tag found but doesn't match '$expected_canonical'"
                    echo -e "${RED}✗${NC} $error"
                    ERRORS+=("$error")
                    ((FAILED++))
                    return 1
                fi
            else
                echo -e "${GREEN}✓${NC} $name"
                ((PASSED++))
                return 0
            fi
        else
            local error="$name - No canonical tag found"
            echo -e "${RED}✗${NC} $error"
            ERRORS+=("$error")
            ((FAILED++))
            return 1
        fi
    else
        local error="$name - HTTP $status (expected 200)"
        echo -e "${RED}✗${NC} $error"
        ERRORS+=("$error")
        ((FAILED++))
        return 1
    fi
}

# Run tests
run_tests() {
    echo "Running tests..."
    echo ""

    # Core pages
    echo "--- Listing Pages ---"
    test_page "Homepage" "/" "Pezet"
    test_page "Album List" "/albumy.html" "Jestem Hip Hopem"
    test_page "Premieres" "/premiery.html" "Stasiak"
    test_page "Artist List" "/wykonawcy.html" "Eldo"
    test_page "Label List" "/wytwornie.html" "Asfalt"
    echo ""

    # Detail pages (using known data from test database)
    echo "--- Detail Pages ---"
    test_page "Label Detail (Alkopoligamia)" "/alkopoligamia-l58.html" "Alkopoligamia"
    test_page "Artist Detail (Mes)" "/mes-p35.html" "Piotr  Szmidt"
    test_page_multi "Album Detail (Wdowa - Superextra)" "/wdowa-superextra-a535.html" "Wdowa" "Pogoda" "Alkopoligamia"
    test_page_multi "Song Detail (Wdowa - Pogoda)" "/pogoda-s7329.html" "Dj Technik" "Beatmo"
    test_page "News Detail" "/onar-jak-na-pierwszej-plycie-wideo-n1877.html" "Onar wraca z nowym singlem"
    echo ""

    # Search functionality
    echo "--- Search ---"
    test_page_multi "Search (tede)" "/szukaj.html?q=tede" "Mefistotedes" "MercTedes"
    echo ""

    # Rankings
    echo "--- Rankings ---"
    test_page "Top 10 Page" "/top10.html" "Top 10"
    test_element_count "Top 10 List Items" "/top10.html" "<li" 70
    test_element_count "Top 10 Thumbnails" "/top10.html" "thumb" 40
    echo ""

    # User pages
    echo "--- User Pages ---"
    test_page "Login Page" "/uzytkownik/logowanie.html" "Zaloguj"
    echo ""

    # Static pages
    echo "--- Static Pages ---"
    test_page "About Page" "/o-nas.html" "hhbd"
    test_page "Contact Page" "/kontakt.html" "kontakt"
    echo ""

    # Canonical URL tests
    echo "--- Canonical URLs (SEO) ---"
    test_redirect_301 "Album - Wrong slug redirects" "/wrong-slug-a535.html" "/wdowa-superextra-a535.html"
    test_redirect_301 "Artist - Wrong slug redirects" "/wrong-artist-p35.html" "/mes-p35.html"
    test_redirect_301 "Song - Wrong slug redirects" "/wrong-song-s7329.html" "/pogoda-s7329.html"
    test_redirect_301 "Label - Wrong slug redirects" "/wrong-label-l58.html" "/alkopoligamia-l58.html"
    test_redirect_301 "Album - Uppercase redirects" "/WDOWA-SUPEREXTRA-A535.html" "/wdowa-superextra-a535.html"
    test_redirect_301 "Artist - Uppercase redirects" "/MES-P35.html" "/mes-p35.html"
    test_canonical_tag "Album - Has canonical tag" "/wdowa-superextra-a535.html" "/wdowa-superextra-a535.html"
    test_canonical_tag "Artist - Has canonical tag" "/mes-p35.html" "/mes-p35.html"
    test_canonical_tag "Song - Has canonical tag" "/pogoda-s7329.html" "/pogoda-s7329.html"
    test_canonical_tag "Label - Has canonical tag" "/alkopoligamia-l58.html" "/alkopoligamia-l58.html"
    echo ""
}

# Print summary
print_summary() {
    echo "========================================"
    echo "  Results: $PASSED passed, $FAILED failed"
    echo "========================================"

    if [[ $FAILED -gt 0 ]]; then
        echo ""
        echo -e "${RED}Errors:${NC}"
        for error in "${ERRORS[@]}"; do
            echo -e "  ${RED}•${NC} $error"
        done
        echo ""
        echo -e "${RED}SMOKE TESTS FAILED${NC}"
        exit 1
    else
        echo -e "${GREEN}ALL SMOKE TESTS PASSED${NC}"
        exit 0
    fi
}

# Main
wait_for_service
run_tests
print_summary
