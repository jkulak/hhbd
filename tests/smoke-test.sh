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
    # Where the data came from is for an admin's eyes only, at the top of the page (#158)
    test_page_absent "A visitor sees no Discogs credit (Superextra, #158)" "/wdowa-superextra-a535.html" "Data provided by Discogs"
    test_admin_page "An admin sees it, linked, in the sources box (Superextra)" "/wdowa-superextra-a535.html" "widzi to tylko admin" "Data provided by Discogs." "https://www.discogs.com/release/1234567"
    test_admin_page "Discogs's CC0 data asks for no credit, and a page with nothing to show has no box (Jestem Hip Hopem)" "/pezet-jestem-hip-hopem-a1.html" "!Data provided by Discogs" "!admin-sources"
    test_page_multi "An album links where it can be heard" "/wdowa-superextra-a535.html" "https://www.deezer.com/album/302127" "https://music.apple.com/album/1440857781"
    test_page_absent "A visitor sees no Discogs credit on an artist's page (Mes)" "/mes-p35.html" "Data provided by Discogs"
    test_admin_page "An admin sees the artist's Discogs credit, linked (Mes)" "/mes-p35.html" "Data provided by Discogs." "https://www.discogs.com/artist/271903"
    test_admin_page "An admin sees the sources and the Discogs notice on the about page" "/o-nas.html" "widzi to tylko admin" "not affiliated with, sponsored or endorsed by Discogs"
    test_page "Another name stored mangled reads right (#27)" "/mes-p35.html" "JŹW"
    test_page "A news item shows its image from content/news/ (#133)" "/premiera-nowego-albumu-pezeta-n1.html" 'id="news-attachment" src="/content/news/test-news-001.jpg"'
    test_page_200 "and nginx serves it" "/content/news/test-news-001.jpg"
    test_redirect_301 "An old address's underscore finds a slug written with a dash (#26)" "/n/dj_technik" "/dj-technik-p6.html"
    test_not_found "A song on no album and by no artist is a 404, not a 500 (#37)" "/bez-albumu-s9100.html" "Call to a member function"
    test_page_absent "and the song sitemap leaves it out (#147)" "/sitemap-songs.xml" "-s9100.html<"
    test_page_absent "A news excerpt that was not cut gets no dots (#163)" "/" 'doczekać!\.\.\.'
    # Passwords and the comment question (#41): these log in and post, so on the fixtures only
    test_login "An account from before #41 logs in with its MD5 and the old salt" "legacy@example.com" "legacypass"
    test_login "and again, with the password hash that login wrote" "legacy@example.com" "legacypass"
    test_login "A wrong password logs nobody in" "legacy@example.com" "wrongpass" refused
    test_login "The admin logs in with a password hash" "admin@example.com" "adminpass"
    test_captcha_once "A comment's question is answered once, and the same answer again is refused"
    test_page "A search with Polish letters finds the name (#151)" "/szukaj.html?q=Sok%C3%B3%C5%82" 'href="/sokol-p10.html"'
    test_page "and so does one without them" "/szukaj.html?q=sokol" 'href="/sokol-p10.html"'
    test_page "and one in capitals" "/szukaj.html?q=SOK%C3%93%C5%81" 'href="/sokol-p10.html"'
    test_page "and one with ó as o and a combining accent" "/szukaj.html?q=Soko%CC%81%C5%82" 'href="/sokol-p10.html"'
    test_page "ł typed as l finds Łona" "/szukaj.html?q=lona" 'href="/lona-p19.html"'
    test_page "An album's title is found without its Polish letters" "/szukaj.html?q=podroz" 'href="/sokol-podroz-zwana-zyciem-a6.html"'
    test_page "A query in ISO-8859-2 from an old link is read as such" "/szukaj.html?q=Sok%F3%B3" "Szukałeś: Sokół"
    test_page_absent "and does not match every row" "/szukaj.html?q=Sok%F3%B3" 'href="/pezet-p1.html"'
    test_page "A song's video kept as a Flash address plays in YouTube's player (#149)" "/pogoda-s7329.html" 'src="https://www.youtube-nocookie.com/embed/M7lc1UVf-VE?enablejsapi=1"'
    test_page_absent "No measurement id here, so no analytics loads (#161)" "/" "googletagmanager.com/gtag/js"
    test_page_absent "and nothing asks for Flash" "/pogoda-s7329.html" "x-shockwave-flash"
    test_page "An artist's meta description is its description's text (#147)" "/mes-p35.html" 'name="description" content="Raper z Krakowa, &quot;Fach&quot;."'
    test_page "A song's is its lyrics' lines with a comma between" "/pogoda-s7329.html" 'name="description" content="Tekst i teledysk utworu Wdowa - Pogoda. Słońce świeci jasno nad miastem..., A my na ławce"'
    test_page "A news item's is its text without the tags" "/onar-jak-na-pierwszej-plycie-wideo-n1877.html" 'name="description" content="Onar wraca z nowym singlem promującym jego najnowszy album. Artysta prezentuje świeży materiał, który nawiązuje do jego wcześniejszej twórczości."'
    test_page_absent "A visitor sees no photo's author (Mes, #158)" "/mes-p35.html" "Jan Kowalski"
    test_page_absent "nor its licence" "/mes-p35.html" "creativecommons.org"
    test_page_absent "nor the gallery's credits" "/mes-p35.html" "Anna Nowak"
    test_page_multi "The artist's other photos are still in a gallery (Mes)" "/mes-p35.html" "Zdjęcia" 'class="gallery"'
    test_admin_page "An admin sees every photo's credit, the main one first (Mes)" "/mes-p35.html" "Zdjęcie główne: Fot." "Jan Kowalski" "https://creativecommons.org/licenses/by-sa/4.0/" "Anna Nowak" "(zmodyfikowane)"
    test_page "A joint album links both its artists" "/pezet-jestem-hip-hopem-a1.html" "&amp; <a href"
    test_page "A joint album is listed on each artist's page, named after both" "/eldo-p2.html" "Pezet & Eldo - Jestem Hip Hopem"
    test_page_multi "An artist sharing a name has a page, title and slug with the qualifier" "/solar-sbm-label-p64.html" "<h1>Solar (SBM Label)</h1>" 'og:title" content="Solar (SBM Label)"'
    test_page_multi "And so has the other artist of that name" "/solar-raper-z-poznania-p65.html" "<h1>Solar (raper z Poznania)</h1>" 'og:title" content="Solar (raper z Poznania)"'
    test_redirect_301 "A namesake's slug without the qualifier redirects to the one with it" "/solar-p64.html" "/solar-sbm-label-p64.html"
    test_page "A landscape main photo carries its size and is not marked portrait (#166)" "/eldo-p2.html" 'width="600" height="378" alt="Zdjęcie Eldo" title="Eldo" class="image"'
    test_page "A portrait main photo is marked so, to stand 300 px high" "/stasiak-p3.html" 'class="image portrait"'
    test_page "An artist named in Cyrillic has a page, under a transcribed slug (#155)" "/igroki-ulic-p67.html" "<h1>Игроки Улиц</h1>"
    test_redirect_301 "and the address the empty slug made of it before leads there" "/x-p67.html" "/igroki-ulic-p67.html"
    test_redirect_301 "A name with an umlaut keeps the letter's base in its slug" "/w-yza-p68.html" "/woyza-p68.html"
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

# test_page_status <name> <path> <status> <text>: the path answers that status, without
# following a redirect, and the body contains the text
test_page_status() {
    local name="$1" path="$2" expected_status="$3" expected="$4"
    local url="${BASE_URL}${path}"
    local status content
    status=$(curl -s -o /dev/null -w "%{http_code}" --max-time 10 $CURL_OPTS "$url" 2>/dev/null || echo "000")
    content=$(curl -s --max-time 10 $CURL_OPTS "$url" 2>/dev/null)
    if [[ "$status" == "$expected_status" ]] && echo "$content" | grep -qi -- "$expected"; then
        echo -e "${GREEN}✓${NC} $name"
        ((PASSED++))
        return 0
    fi
    local error="$name - HTTP $status (expected $expected_status with '$expected')"
    echo -e "${RED}✗${NC} $error"
    ERRORS+=("$error")
    ((FAILED++))
    return 1
}

# test_sitemap <name> <path> <urlset|sitemapindex>: XML a search engine reads (#147): served as
# application/xml, nothing after the root element closes, every entry closed and with one <loc>,
# and every <loc> an absolute address on this site: https:// ones on production
test_sitemap() {
    local name="$1" path="$2" root="$3"
    local url="${BASE_URL}${path}" entry=url
    [[ "$root" == sitemapindex ]] && entry=sitemap
    local type body opens closes locs ours last problem=""
    type=$(curl -s -D - -o /dev/null --max-time 30 $CURL_OPTS "$url" 2>/dev/null | tr -d '\r' | grep -i '^content-type:' | head -1)
    body=$(curl -s --max-time 30 $CURL_OPTS "$url" 2>/dev/null)
    opens=$(echo "$body" | grep -o "<$entry>" | wc -l | tr -d ' ')
    closes=$(echo "$body" | grep -o "</$entry>" | wc -l | tr -d ' ')
    locs=$(echo "$body" | grep -o '<loc>' | wc -l | tr -d ' ')
    ours=$(echo "$body" | grep -o "<loc>${BASE_URL}/[^<]*</loc>" | wc -l | tr -d ' ')
    last=$(echo "$body" | grep -v '^[[:space:]]*$' | tail -1 | tr -d '[:space:]')
    if [[ "$type" != *application/xml* ]]; then
        problem="served as '${type#*: }'"
    elif [[ "$(echo "$body" | head -1)" != "<?xml"* ]]; then
        problem="no XML declaration first"
    elif [[ "$last" != "</$root>" ]]; then
        problem="ends with '$last', not </$root>"
    elif [[ "$opens" -eq 0 || "$opens" != "$closes" || "$opens" != "$locs" ]]; then
        problem="$opens <$entry>, $closes </$entry>, $locs <loc>"
    elif [[ "$ours" != "$locs" ]]; then
        problem="$((locs - ours)) of $locs <loc> not under ${BASE_URL}/"
    fi
    if [[ -z "$problem" ]]; then
        echo -e "${GREEN}✓${NC} $name ($locs addresses)"
        ((PASSED++))
        return 0
    fi
    local error="$name - $problem"
    echo -e "${RED}✗${NC} $error"
    ERRORS+=("$error")
    ((FAILED++))
    return 1
}

# md5_of: the MD5 of stdin, with md5sum (Linux) or md5 (macOS)
md5_of() {
    if command -v md5sum >/dev/null 2>&1; then md5sum | cut -d' ' -f1; else md5 -q; fi
}

# test_asset <name> <path>: the home page links the file at an address ending in the start of
# its MD5, so a changed file is a new address that no cache holds (#149)
test_asset() {
    local name="$1" path="$2"
    local address hash
    address=$(curl -s --max-time 10 $CURL_OPTS "${BASE_URL}/" 2>/dev/null | grep -o "${path}?v=[0-9a-f]*" | head -1)
    hash=$(curl -s --max-time 10 $CURL_OPTS "${BASE_URL}${path}" 2>/dev/null | md5_of | cut -c1-8)
    if [[ -n "$hash" && "$address" == "${path}?v=${hash}" ]]; then
        echo -e "${GREEN}✓${NC} $name"
        ((PASSED++))
        return 0
    fi
    local error="$name - linked as '$address', the file's MD5 starts '$hash'"
    echo -e "${RED}✗${NC} $error"
    ERRORS+=("$error")
    ((FAILED++))
    return 1
}

# test_login <name> <email> <password> [refused]: the login form lets the account in, the home
# page then says who is logged in; with "refused", it lets nobody in
test_login() {
    local name="$1" email="$2" password="$3" expect="${4:-in}"
    local jar status seen
    jar=$(mktemp "${TMPDIR:-/tmp}/hhbd-smoke-jar.XXXXXX")
    status=$(curl -s -o /dev/null -w "%{http_code}" --max-time 10 $CURL_OPTS -c "$jar" -b "$jar" \
        --data-urlencode "email=$email" --data-urlencode "password=$password" "${BASE_URL}/uzytkownik/logowanie.html" 2>/dev/null)
    seen=$(curl -s --max-time 10 $CURL_OPTS -b "$jar" "${BASE_URL}/" 2>/dev/null | grep -c "Zalogowany jako")
    rm -f "$jar"
    if { [[ "$expect" == in && "$status" == 302 && "$seen" -gt 0 ]]; } || { [[ "$expect" == refused && "$status" == 200 && "$seen" == 0 ]]; }; then
        echo -e "${GREEN}✓${NC} $name"
        ((PASSED++))
        return 0
    fi
    local error="$name - login answered $status, logged in on the home page: $seen"
    echo -e "${RED}✗${NC} $error"
    ERRORS+=("$error")
    ((FAILED++))
    return 1
}

# test_captcha_once <name>: a question from the server, a comment with its answer, and the same
# answer again, which is refused (#41)
test_captcha_once() {
    local name="$1" challenge token sum first again
    local xhr="X-Requested-With: XMLHttpRequest"
    challenge=$(curl -s --max-time 10 $CURL_OPTS -X POST -H "$xhr" "${BASE_URL}/comments/captcha" 2>/dev/null)
    token=$(echo "$challenge" | sed -nE 's/.*"token":"([0-9a-f]{32})".*/\1/p')
    sum=$(echo "$challenge" | sed -nE 's/.*Ile to ([0-9]+) \\?\+ ([0-9]+).*/\1 \2/p' | awk '{print $1 + $2}')
    comment() {
        curl -s -o /dev/null -w "%{http_code}" --max-time 10 $CURL_OPTS -X POST -H "$xhr" \
            --data-urlencode "content=$1" --data-urlencode "author=smoke" --data-urlencode "captcha_token=$token" \
            --data-urlencode "captcha_answer=$sum" --data-urlencode "com_object_id=535" --data-urlencode "com_object_type=a" \
            --data-urlencode "form_time=$(( $(date +%s) - 10 ))" --data-urlencode "email-honey-pot=" "${BASE_URL}/comments" 2>/dev/null
    }
    first=$(comment "Smoke test: a comment with the question answered")
    again=$(comment "Smoke test: the same answer again")
    if [[ -n "$token" && -n "$sum" && "$first" == 200 && "$again" == 422 ]]; then
        echo -e "${GREEN}✓${NC} $name"
        ((PASSED++))
        return 0
    fi
    local error="$name - question '$challenge', the answer answered $first, the replay $again"
    echo -e "${RED}✗${NC} $error"
    ERRORS+=("$error")
    ((FAILED++))
    return 1
}

# as_admin <path>: the page as the fixtures' admin sees it, logged in with a cookie of its own
as_admin() {
    local jar page
    jar=$(mktemp "${TMPDIR:-/tmp}/hhbd-smoke-admin.XXXXXX")
    curl -s -o /dev/null --max-time 10 $CURL_OPTS -c "$jar" -b "$jar" \
        --data-urlencode "email=admin@example.com" --data-urlencode "password=adminpass" "${BASE_URL}/uzytkownik/logowanie.html" 2>/dev/null
    page=$(curl -s --max-time 10 $CURL_OPTS -b "$jar" "${BASE_URL}$1" 2>/dev/null)
    rm -f "$jar"
    echo "$page"
}

# test_admin_page <name> <path> <text...>: as the fixtures' admin, the page holds every text;
# "!text" means it must not
test_admin_page() {
    local name="$1" path="$2"
    shift 2
    local page text missing=""
    page=$(as_admin "$path")
    for text in "$@"; do
        if [[ "$text" == '!'* ]]; then
            echo "$page" | grep -qiF -- "${text#!}" && missing="$missing present:'${text#!}'"
        else
            echo "$page" | grep -qiF -- "$text" || missing="$missing missing:'$text'"
        fi
    done
    if [[ -z "$missing" ]]; then
        echo -e "${GREEN}✓${NC} $name"
        ((PASSED++))
        return 0
    fi
    local error="$name -$missing"
    echo -e "${RED}✗${NC} $error"
    ERRORS+=("$error")
    ((FAILED++))
    return 1
}

# test_stylesheet <name> <present> [absent]: s.css, its whitespace folded to single spaces,
# holds the first text and not the second
test_stylesheet() {
    local name="$1" present="$2" absent="${3:-}" css
    css=$(curl -s --max-time 10 $CURL_OPTS "${BASE_URL}/css/s.css" 2>/dev/null | tr -s ' \n\t' '   ')
    if [[ "$css" == *"$present"* && ( -z "$absent" || "$css" != *"$absent"* ) ]]; then
        echo -e "${GREEN}✓${NC} $name"
        ((PASSED++))
        return 0
    fi
    local error="$name - s.css lacks '$present' or holds '$absent'"
    echo -e "${RED}✗${NC} $error"
    ERRORS+=("$error")
    ((FAILED++))
    return 1
}

run_tests() {
    echo "Running tests..."
    echo ""

    # Core pages
    echo "--- Listing Pages ---"
    test_page "Homepage" "/" "Pezet"
    test_page_absent "The home page's news excerpts split no letter (#163)" "/" "�"
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
    test_page_absent "A visitor sees no sources paragraph on the about page (#158)" "/o-nas.html" "not affiliated with, sponsored or endorsed by Discogs"
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
    test_page "A search shows markup in its query as text (#151)" "/szukaj.html?q=%3Cb%3Ex%3C%2Fb%3E" "Szukałeś: &lt;b&gt;x&lt;/b&gt;"
    test_page_absent "and never as markup" "/szukaj.html?q=%3Cb%3Ex%3C%2Fb%3E" "<b>x</b>"
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

    # What a search engine reads first, and what it finds (#147)
    echo "--- Search Engines ---"
    test_page "robots.txt names the sitemap index" "/robots.txt" "^Sitemap: https://hhbd.pl/sitemap-index.xml$"
    test_page_absent "robots.txt keeps no search engine out of the whole site" "/robots.txt" "^Disallow: /$"
    test_sitemap "The sitemap index" "/sitemap-index.xml" sitemapindex
    test_page "The sitemap index names the song sitemap at an absolute address" "/sitemap-index.xml" "<loc>${BASE_URL}/sitemap-songs.xml</loc>"
    test_sitemap "The album sitemap" "/sitemap-albums.xml" urlset
    test_sitemap "The artist sitemap" "/sitemap-artists.xml" urlset
    test_sitemap "The song sitemap" "/sitemap-songs.xml" urlset
    test_sitemap "The label sitemap" "/sitemap-labels.xml" urlset
    test_sitemap "The news sitemap" "/sitemap-news.xml" urlset
    test_not_found "A sitemap of no kind is a 404" "/sitemap-nothing.xml" "<urlset"
    test_page "The album sitemap's addresses are the canonical ones, scheme and all" "/sitemap-albums.xml" "<loc>${BASE_URL}/wdowa-superextra-a535.html</loc>"
    test_canonical_tag "An album's canonical tag has this site's scheme" "/wdowa-superextra-a535.html" "${BASE_URL}/wdowa-superextra-a535.html"
    test_not_found "A missing album is a 404 at its own address, not a redirect" "/nie-ma-takiego-a999999999.html" "Zend Framework"
    test_not_found "A missing artist is a 404 at its own address" "/nie-ma-takiego-p999999999.html" "Zend Framework"
    test_not_found "A missing song is a 404 at its own address" "/nie-ma-takiego-s999999999.html" "Zend Framework"
    test_not_found "A missing label is a 404 at its own address" "/nie-ma-takiej-l999999999.html" "Zend Framework"
    test_not_found "A missing news item is a 404 at its own address" "/nie-ma-takiego-n999999999.html" "Zend Framework"
    test_page_status "The 404 page has a title of its own" "/nie-ma-takiego-a999999999.html" 404 "<title>Nie ma takiej strony - Hhbd.pl</title>"
    test_page_status "and asks not to be indexed" "/nie-ma-takiego-a999999999.html" 404 'name="robots" content="noindex,follow"'
    test_page "Search results ask not to be indexed" "/szukaj.html?q=tede" 'name="robots" content="noindex,follow"'
    test_page "The login page asks not to be indexed" "/uzytkownik/logowanie.html" 'name="robots" content="noindex,follow"'
    test_page "An album's page asks to be indexed" "/wdowa-superextra-a535.html" 'name="robots" content="index,follow"'
    test_page_absent "An artist's meta description holds no HTML" "/mes-p35.html" '&lt;p'
    test_page_absent "A song's meta description holds none of its lyrics' line breaks" "/pogoda-s7329.html" '&lt;br'
    test_page_absent "A news item's meta description holds no HTML" "/onar-jak-na-pierwszej-plycie-wideo-n1877.html" 'name="description" content="[^"]*&lt;'
    echo ""

    # Google's tags (#161, #162): consent first, everything denied; nothing of the old tags
    echo "--- Analytics and consent ---"
    test_page "Google's tags start with every consent denied (#162)" "/" "gtag('consent', 'default', {ad_storage: 'denied', ad_user_data: 'denied', ad_personalization: 'denied', analytics_storage: 'denied'"
    test_page_absent "Universal Analytics, dead since 2023, is gone (#161)" "/" "UA-3311418"
    test_page_absent "and so is Tag Manager" "/" "GTM-MGJ9HQ"
    test_page "The footer opens the privacy settings" "/" 'id="privacy-settings">Ustawienia prywatności</a>'
    test_page "The privacy page says what is measured" "/prywatnosc.html" "Google Analytics 4"
    test_page "ads.txt names hhbd's AdSense account (#169)" "/ads.txt" "google.com, pub-6149271850793027, DIRECT, f08c47fec0942fa0"
    test_page "and every page says it belongs to that account" "/" '<meta name="google-adsense-account" content="ca-pub-6149271850793027">'
    test_page "The side column's ad is AdSense's asynchronous unit, in its old slot (#172)" "/" 'data-ad-client="ca-pub-6149271850793027" data-ad-slot="1220656090"></ins>'
    test_page_absent "and nothing loads the 2010 script that held the page up" "/" "show_ads\.js"
    test_page_absent "nor sets its globals" "/wdowa-superextra-a535.html" "google_ad_"
    echo ""

    # Phones (#149)
    echo "--- Phones ---"
    test_page "A page is as wide as the screen it is on" "/" 'name="viewport" content="width=device-width, initial-scale=1"'
    test_page "The header has the phone's menu button" "/" 'id="menu-toggle" aria-controls="nav" aria-expanded="false"'
    test_asset "The stylesheet's address carries its hash" "/css/s.css"
    test_asset "The script's address carries its hash" "/js/s.js"
    test_page_absent "The stylesheet minified once in January is gone" "/" "s.min.css"
    test_page "The album list's table has its class for the phone's layout" "/albumy.html" 'class="album-table"'
    test_page "A track's artists are in a box of limited width (#156)" "/wdowa-superextra-a535.html" '<span class="artists">'
    test_stylesheet "A cover or main photo keeps its proportions, its height no longer fixed (#166)" "#picture img { width: 300px; height: auto; }" "#picture img { width: 300px; height: 300px; }"
    test_page_absent "No page loads jQuery, 1.4.4 or any other (#43)" "/wdowa-superextra-a535.html" "jquery"
    test_page "The site's script runs once the page is parsed" "/" '<script src="/js/s.js?v=[0-9a-f]*" defer>'
    test_page_absent "The comment form carries no inline script" "/wdowa-superextra-a535.html" "limitChars"
    test_page_absent "The comment form carries no answer, hashed or not (#41)" "/wdowa-superextra-a535.html" "captcha_hash"
    test_page_status "A comment's question is not made by a GET" "/comments/captcha" 405 "POST only"
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

# What only production has: its measurement id (#161)
run_production_tests() {
    echo "--- Production ---"
    test_page "GA4 loads with production's measurement id (#161)" "/" "googletagmanager.com/gtag/js?id=G-200N6YNR76"
    test_page "An album's page view says what the page is and which album" "/wdowa-superextra-a535.html" '"content_group":"album","entity_id":535'
    echo ""
}

# Main
wait_for_service
run_tests
case "$SMOKE_TARGET" in
    fixtures) run_fixture_tests ;;
    production) run_production_tests; echo "--- Fixture cases: skipped, SMOKE_TARGET=production ---"; echo "" ;;
    *) echo "SMOKE_TARGET is fixtures or production, not '$SMOKE_TARGET'" >&2; exit 2 ;;
esac
print_summary
