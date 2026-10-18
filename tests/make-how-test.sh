#!/usr/bin/env bash
#
# make how against make help: it names targets by their names, and a target renamed or removed
# would leave a step pointing at nothing. Needs neither the stack nor Docker.
#
# What it proves:
#   - make help lists how right after help
#   - make how prints numbered steps, each opening with the target that carries it out
#   - every target make how names is one make help lists, and a name it does not list is caught
#   - make how stays well under an A4 page
#
# Usage: tests/make-how-test.sh
#
set -euo pipefail
cd "$(git rev-parse --show-toplevel)"

pass=0
fail=0
ok()  { echo "ok   $1"; pass=$((pass + 1)); }
bad() { echo "x    $1"; fail=$((fail + 1)); }
check() { if [ "$2" = "$3" ]; then ok "$1"; else bad "$1"; echo "     expected: $2"; echo "     got:      $3"; fi; }

# make help's target names, its bold taken off
targets=$(make -s --no-print-directory help | tr -d '\033' | sed -E 's/\[[0-9;]*m//g' | awk '{ print $1 }')
how=$(make -s --no-print-directory how)

# The names in "make <name>" that make help does not list, one a line
unknown() {
    grep -oE '(^|[^[:alnum:]_-])make [a-z][a-z0-9-]*' | sed -E 's/.*make //' | sort -u \
        | grep -vxF -f <(printf '%s\n' "$targets") || true
}

check "make help lists how right after help" "help how" "$(head -2 <<<"$targets" | tr '\n' ' ' | sed 's/ $//')"

steps=$(grep -cE '^  [0-9]+\. make [a-z]' <<<"$how" || true)
if [ "$steps" -ge 5 ]; then ok "make how prints numbered steps, each opening with a target ($steps)"; else
    bad "make how prints numbered steps, each opening with a target"; echo "     got $steps"; fi
check "no numbered step of make how opens without a target" "" "$(grep -E '^  [0-9]+\. ' <<<"$how" | grep -vE '^  [0-9]+\. make [a-z]' || true)"

check "every target make how names is one make help lists" "" "$(unknown <<<"$how")"
check "a name make help does not list is caught" "no-such-target" "$(printf '%s\nmake no-such-target\n' "$how" | unknown)"

lines=$(wc -l <<<"$how" | tr -d ' ')
if [ "$lines" -le 45 ]; then ok "make how stays well under an A4 page ($lines lines)"; else
    bad "make how stays well under an A4 page"; echo "     got $lines lines"; fi

echo ""
echo "$pass passed, $fail failed"
[ "$fail" -eq 0 ]
