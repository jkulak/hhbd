#!/bin/bash
#
# HHBD Smoke Tests
# Verifies that key pages are accessible and display database content
#
# Usage:
#   ./tests/smoke-test.sh              # Test localhost:8080
#   ./tests/smoke-test.sh http://example.com  # Test custom URL
#
#   # A host the name does not point at yet, e.g. a new origin before the DNS moves:
#   SMOKE_CURL_OPTS="--connect-to hhbd.pl:443:<host>:443 --insecure" ./tests/smoke-test.sh https://hhbd.pl
#
#   # Production, as a release checks it: only what holds on production's data
#   SMOKE_TARGET=production ./tests/smoke-test.sh https://hhbd.pl
#
# Most checks hold on the test fixtures and on production alike: the fixtures copy the rows the
# checks look at (Mes, Superextra, Pogoda, Alkopoligamia, news 1877). The fixture checks look at
# cases only the fixtures hold (an album on no label, a two-disc album, Discogs provenance, ...)
# and run unless SMOKE_TARGET=production, which deploy/ovh/smoke.sh sets.
#

BASE_URL="${1:-http://localhost:8080}"
SMOKE_TARGET="${SMOKE_TARGET:-fixtures}"
FAILED=0
PASSED=0
ERRORS=()  # Array to collect error messages

# Extra curl options from the environment, word-split on purpose so several can be given.
# Without them, detect if we need to add Host header (for docker service name connections)
CURL_OPTS="${SMOKE_CURL_OPTS:-}"
if [[ -z "$CURL_OPTS" ]] && { [[ "$BASE_URL" == *"nginx"* ]] || [[ "$BASE_URL" == *"172.18"* ]]; }; then
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

        if echo "$content" | grep -qi -- "$expected"; then
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
# test_page_absent <name> <path> <text>: the page answers 200 and does not contain the text
test_page_absent() {
    local name="$1"
    local path="$2"
    local unexpected="$3"
    local url="${BASE_URL}${path}"
    local status content
    status=$(curl -s -o /dev/null -w "%{http_code}" --max-time 10 $CURL_OPTS "$url" 2>/dev/null || echo "000")
    if [[ "$status" != "200" ]]; then
        local error="$name - HTTP $status (expected 200)"
        echo -e "${RED}✗${NC} $error"
        ERRORS+=("$error")
        ((FAILED++))
        return 1
    fi
    content=$(curl -s --max-time 10 $CURL_OPTS "$url" 2>/dev/null)
    if echo "$content" | grep -qi -- "$unexpected"; then
        local error="$name - Content present: '$unexpected'"
        echo -e "${RED}✗${NC} $error"
        ERRORS+=("$error")
        ((FAILED++))
        return 1
    fi
    echo -e "${GREEN}✓${NC} $name"
    ((PASSED++))
    return 0
}

wait_for_service() {
    local max_attempts=5
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
        count=$(echo "$content" | grep -oE "$pattern" | wc -l)

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

# Test that page doesn't contain + symbols in internal URLs
test_no_plus_in_urls() {
    local name="$1"
    local path="$2"
    local url="${BASE_URL}${path}"

    # Get HTTP status code
    local status
    status=$(curl -s -o /dev/null -w "%{http_code}" --max-time 10 $CURL_OPTS "$url" 2>/dev/null || echo "000")

    if [[ "$status" == "200" ]]; then
        local content
        content=$(curl -s --max-time 10 $CURL_OPTS "$url" 2>/dev/null)

        # Check for + symbols in href attributes for entity URLs
        # Look for patterns like: href="/...-a123.html" or href="/...-p456.html" containing +
        if echo "$content" | grep -E 'href="[^"]*\+[^"]*-[aps][0-9]+\.html"' > /dev/null; then
            local error="$name - Contains + symbols in entity URLs"
            echo -e "${RED}✗${NC} $error"
            ERRORS+=("$error")
            ((FAILED++))
            return 1
        fi

        echo -e "${GREEN}✓${NC} $name (no + in URLs)"
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

# Run tests
# Cases only the test fixtures hold; skipped when SMOKE_TARGET=production.
run_fixture_tests() {
    echo "--- Fixture cases ---"
    test_page_multi "Artist city from the old backoffice table (Mes)" "/mes-p35.html" "Miasto:" "Kraków"
    test_page_multi "Album media (Superextra)" "/wdowa-superextra-a535.html" "Nośniki:" "CD, LP"
    test_page "Release type: a single" "/pezet-muzyka-powazna-a2.html" "[singiel]"
    test_page "Release type: an EP" "/taco-hemingway-morska-bryza-a46.html" "[EP]"
    test_page "A nielegal" "/zabson-lesna-sciezka-a47.html" "[nielegal]"
    test_page "An album without a label renders" "/zabson-lesna-sciezka-a47.html" "Wydawnictwo:"
    test_page "An album without a label is in its artist's list" "/zabson-p50.html" "Leśna Ścieżka"
    test_page "An album that was on the placeholder label renders" "/borixon-miejski-rytm-a48.html" "Wydawnictwo:"
    test_page_absent "The label list has no placeholder label" "/wytwornie.html" ">BRAK<"
    test_page_absent "The 2017 placeholder is gone from the album list" "/albumy.html" "Korzenie"
    test_page "An album dated only by its year shows the year" "/eldo-podmiejski-gwar-a50.html" "Premiera:</span> 2013"
    test_page "An album with no known date renders" "/eldo-polskie-karate-a778.html" "Polskie Karate"
    test_page_multi "A two-disc tracklist is numbered by disc" "/eldo-trzecia-czesc-tryptyku-a3.html" "1-01" "2-01"
    test_page_multi "Discogs data is credited and linked (Superextra)" "/wdowa-superextra-a535.html" "Data provided by Discogs." "https://www.discogs.com/release/1234567"
    test_page_absent "Discogs's CC0 data is not credited (Jestem Hip Hopem)" "/pezet-jestem-hip-hopem-a1.html" "Data provided by Discogs"
    test_page_multi "An album links where it can be heard" "/wdowa-superextra-a535.html" "https://www.deezer.com/album/302127" "https://music.apple.com/album/1440857781"
    test_page "An artist's Discogs data is credited (Mes)" "/mes-p35.html" "https://www.discogs.com/artist/271903"
    test_page "Another name stored mangled reads right (#27)" "/mes-p35.html" "JŹW"
    test_redirect_301 "An old address's underscore finds a slug written with a dash (#26)" "/n/dj_technik" "/dj-technik-p6.html"
    test_not_found "A song on no album and by no artist is a 404, not a 500 (#37)" "/bez-albumu-s9100.html" "Call to a member function"
    test_page_multi "An artist's main photo carries its credit and licence (Mes)" "/mes-p35.html" "Jan Kowalski" "https://creativecommons.org/licenses/by-sa/4.0/"
    test_page_multi "An artist's other photos are in a gallery, captioned (Mes)" "/mes-p35.html" "Zdjęcia" "Anna Nowak" "(zmodyfikowane)"
    test_page "A joint album links both its artists" "/pezet-jestem-hip-hopem-a1.html" "&amp; <a href"
    test_page "A joint album is listed on each artist's page, named after both" "/eldo-p2.html" "Pezet & Eldo - Jestem Hip Hopem"
    test_page_multi "An artist sharing a name has a page, title and slug with the qualifier" "/solar-sbm-label-p64.html" "<h1>Solar (SBM Label)</h1>" 'og:title" content="Solar (SBM Label)"'
    test_page_multi "And so has the other artist of that name" "/solar-raper-z-poznania-p65.html" "<h1>Solar (raper z Poznania)</h1>" 'og:title" content="Solar (raper z Poznania)"'
    test_redirect_301 "A namesake's slug without the qualifier redirects to the one with it" "/solar-p64.html" "/solar-sbm-label-p64.html"
    test_page_multi "A search finding both shows each one's qualifier" "/szukaj.html?q=Solar" "Solar (SBM Label)" "Solar (raper z Poznania)"
    test_page "A page listing one of them links him" "/sklad-solara-p66.html" 'href="/solar-sbm-label-p64.html"'
    test_page_absent "under his name alone" "/sklad-solara-p66.html" "(SBM Label)"
    # What an import left to settle (#103): nothing for a visitor, a panel for the fixtures' admin.
    test_page_absent "A visitor sees no review panel" "/solar-raper-z-poznania-p65.html" "Do przejrzenia"
    local jar visitor_opts="$CURL_OPTS"
    jar=$(mktemp)
    curl -s -o /dev/null --max-time 10 $CURL_OPTS -c "$jar" --data-urlencode "email=admin@example.com" --data-urlencode "password=adminpass" "$BASE_URL/uzytkownik/logowanie.html"
    CURL_OPTS="$visitor_opts -b $jar"
    test_page_multi "An admin sees a namesake's doubt and the artist it may be" "/solar-raper-z-poznania-p65.html" "Do przejrzenia" "Połącz z: Solar (SBM Label) (id 64)" "To inny wykonawca"
    test_page_multi "And an album's disputed date with the sources' values" "/eldo-podmiejski-gwar-a50.html" "Źródła nie zgadzają się co do daty" "Data: 2013-05-17"
    test_page_multi "And a stand-in cover" "/wdowa-superextra-a535.html" "Okładka zastępcza" "Ta okładka zostaje"
    test_page_multi "The list counts the open items by reason" "/admin/do-przejrzenia.html" 'data-reason="namesake">1<' 'data-reason="single_source">1<' "Solar (raper z Poznania)"
    CURL_OPTS="$visitor_opts"
    rm -f "$jar"
    echo ""
}

# test_redirect_302 <name> <path> <location>: the path answers 302 to a location ending in the one given
test_redirect_302() {
    local name="$1"
    local path="$2"
    local expected_location="$3"
    local url="${BASE_URL}${path}"
    local status location
    status=$(curl -s -o /dev/null -w "%{http_code}" --max-time 10 $CURL_OPTS "$url" 2>/dev/null || echo "000")
    location=$(curl -s -o /dev/null -w "%{redirect_url}" --max-time 10 $CURL_OPTS "$url" 2>/dev/null)
    if [[ "$status" == "302" && "$location" == *"$expected_location" ]]; then
        echo -e "${GREEN}✓${NC} $name (redirects to $expected_location)"
        ((PASSED++))
        return 0
    fi
    local error="$name - HTTP $status to '$location' (expected 302 to '$expected_location')"
    echo -e "${RED}✗${NC} $error"
    ERRORS+=("$error")
    ((FAILED++))
    return 1
}

# test_not_found <name> <path> <text>: the path answers 404 and the body does not contain the text
test_not_found() {
    local name="$1"
    local path="$2"
    local unexpected="$3"
    local url="${BASE_URL}${path}"
    local status content
    status=$(curl -s -o /dev/null -w "%{http_code}" --max-time 10 $CURL_OPTS "$url" 2>/dev/null || echo "000")
    if [[ "$status" != "404" ]]; then
        local error="$name - HTTP $status (expected 404)"
        echo -e "${RED}✗${NC} $error"
        ERRORS+=("$error")
        ((FAILED++))
        return 1
    fi
    content=$(curl -s --max-time 10 $CURL_OPTS "$url" 2>/dev/null)
    if echo "$content" | grep -qi -- "$unexpected"; then
        local error="$name - Content present: '$unexpected'"
        echo -e "${RED}✗${NC} $error"
        ERRORS+=("$error")
        ((FAILED++))
        return 1
    fi
    echo -e "${GREEN}✓${NC} $name"
    ((PASSED++))
    return 0
}

run_tests() {
    echo "Running tests..."
    echo ""

    # Core pages
    echo "--- Listing Pages ---"
    test_page "Homepage" "/" "Pezet"
    # The table, not a title: production's top album was a 2017 placeholder until #54 removed it.
    test_page "Album List" "/albumy.html" "Lista albumów hip-hopowych"
    test_page "Premieres" "/premiery.html" "Stasiak"
    test_page "Artist List" "/wykonawcy.html" "Eldo"
    test_page "Label List" "/wytwornie.html" "Asfalt"
    echo ""

    # Detail pages (using known data from test database)
    echo "--- Detail Pages ---"
    test_page "Label Detail (Alkopoligamia)" "/alkopoligamia-l58.html" "Alkopoligamia"
    test_page "Artist Detail (Mes)" "/mes-p35.html" "Piotr  Szmidt"
    test_page_multi "Album Detail (Wdowa - Superextra)" "/wdowa-superextra-a535.html" "Wdowa" "Pogoda" "Alkopoligamia"
    test_page "The about page says the site is not affiliated with Discogs" "/o-nas.html" "not affiliated with, sponsored or endorsed by Discogs"
    test_page "The album sitemap gives each album's canonical URL" "/sitemap-albums.xml" "/wdowa-superextra-a535.html</loc>"
    test_page "The song sitemap gives canonical URLs" "/sitemap-songs.xml" "/pogoda-s7329.html</loc>"
    test_page "The label sitemap gives canonical URLs" "/sitemap-labels.xml" "/alkopoligamia-l58.html</loc>"
    test_page "The artist sitemap renders" "/sitemap-artists.xml" "/mes-p35.html</loc>"
    test_page "The news sitemap renders" "/sitemap-news.xml" "wideo-n1877.html</loc>"
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

    # The addresses before the .html ones, still linked from old profiles, news and other sites
    # (#26): the rows below have the same slug here and on production
    echo "--- Old Addresses ---"
    test_redirect_301 "An old artist address (/n/) goes to the artist's page" "/n/Mes" "/mes-p35.html"
    test_page_absent "A visitor does not see who added an album, which only an admin does" "/wdowa-superextra-a535.html" "Dodano:"
    test_redirect_301 "The later form (/wykonawca/), in any case, too" "/wykonawca/mes" "/mes-p35.html"
    test_redirect_301 "An old album address (/a/) goes to the album's page" "/a/superextra" "/wdowa-superextra-a535.html"
    test_redirect_301 "An old label address (/l/) goes to the label's page" "/l/alkopoligamia" "/alkopoligamia-l58.html"
    test_redirect_301 "An old song address (/s/) goes to the song's page" "/s/pogoda" "pogoda-s7329.html"
    test_redirect_301 "An old news address (/news/) goes to the news item" "/news/1877" "-n1877.html"
    test_redirect_302 "An old address naming no row goes to the search for its words" "/n/nie_ma_takiego-wykonawcy" "/szukaj.html?q=nie+ma+takiego+wykonawcy"
    test_not_found "An old news address of no news item is a 404" "/news/999999999" "Cannot assemble"
    echo ""

    # A .php file that does not exist: nginx's own 404, where PHP-FPM said "File not found." and
    # nginx logged an error for every bot asking (#34)
    echo "--- Missing Files ---"
    test_not_found "A .php file that does not exist is nginx's 404, not PHP-FPM's" "/wp-login.php" "File not found."
    test_not_found "No dotfile is served (#139)" "/.htaccess" "RewriteEngine"
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

    # URL format validation (no spaces or + symbols in generated links)
    echo "--- URL Format Validation ---"
    test_no_plus_in_urls "Homepage - Album links" "/"
    test_no_plus_in_urls "Album List - Album links" "/albumy.html"
    test_no_plus_in_urls "Album Detail - Song links" "/wdowa-superextra-a535.html"
    test_no_plus_in_urls "Artist Detail - Album links" "/mes-p35.html"
    test_no_plus_in_urls "Top 10 - Song links" "/top10.html"
    test_no_plus_in_urls "Search Results - All entity links" "/szukaj.html?q=tede"
    test_no_plus_in_urls "Label Detail - Album links" "/alkopoligamia-l58.html"
    test_no_plus_in_urls "Song Detail - Artist/Album links" "/pogoda-s7329.html"
    echo ""

    # Entity getUrl() validation - ensure all entity types render their links correctly
    echo "--- Entity URL Generation ---"
    test_element_count "Album links contain proper slug format" "/albumy.html" '\-a[0-9]' 10
    test_element_count "Artist links contain proper slug format" "/wykonawcy.html" '\-p[0-9]' 10
    test_element_count "Song links on album page" "/wdowa-superextra-a535.html" '\-s[0-9]' 5
    test_element_count "Label links contain proper slug format" "/wytwornie.html" '\-l[0-9]' 5
    test_element_count "News links contain proper slug format" "/" '\-n[0-9]' 3
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
case "$SMOKE_TARGET" in
    fixtures) run_fixture_tests ;;
    production) echo "--- Fixture cases: skipped, SMOKE_TARGET=production ---"; echo "" ;;
    *) echo "SMOKE_TARGET is fixtures or production, not '$SMOKE_TARGET'" >&2; exit 2 ;;
esac
print_summary
