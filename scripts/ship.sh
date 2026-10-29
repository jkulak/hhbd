#!/usr/bin/env bash
# From an open pull request to main, the one way every repo of mine merges: main is one
# straight line, every commit on it is signed with my key, and the merge is a fast-forward push
# of the branch itself. GitHub's merge button and gh pr merge cannot do that, because the
# commits they write carry GitHub's signature or none.
#
#   ship.sh <pr>   check it may go in, rebase it onto main and sign it again where it needs
#                  that, pass its gate, push it to main, then follow what the merge starts
#
# Word for word the same in every repo: the original is the platform's, a shared file of
# its service template, and its `make service-drift` says when a copy has moved. A copy kept up
# by hand falls behind, so one that is not what the platform's main holds runs main's version
# instead, fetched afresh into the platform checkout, which is always on this Mac: a fix to the
# original reaches every repo the moment it is merged there. Only main's, never the checkout's
# working tree or a branch it has out, since that checkout is someone's work in progress. Without
# the checkout, or when its main cannot be fetched, the copy runs itself and says it may be
# behind. What differs between repos is read from the repo itself:
#
#   the gate     the checks of the workflows that run on its pull requests; where none do,
#                make ci on the new head, its line put into the pull request body
#   the release  .github/workflows/release.yml: its run for the merge, the deploy of the tag
#                it made, then production's logs; a make release target: named as the next
#                step; neither: nothing
#
# The rebase and the gate run in a throwaway worktree, so the checkout this is run from is
# never touched: another piece of work may be using it. Squashing commits that do not stand on
# their own is a decision made before this, not by it.
#
# Environment:
#   INSTALLED=1   the host configuration the pull request changes was installed from its branch
#   OVH_PLATFORM  the platform checkout, whose origin/main holds the ship.sh that runs and whose
#                 make logs reads production (default ~/Developer/platform, as in deploy/ovh/ovh.mk)
set -euo pipefail
# Nothing it runs may wait on me. From a terminal git pages what it prints into less, and my
# shell's LESS=-R, unlike the LESS=FRX git sets where there is none, keeps less open on a single
# line until q is pressed. Git would also open an editor for a message, or ask for a user name
# and password over HTTPS, and gh pages and asks questions of its own.
export GIT_PAGER=cat GH_PAGER=cat GIT_EDITOR=true GIT_TERMINAL_PROMPT=0 GH_PROMPT_DISABLED=1
SELF=$(cd "$(dirname "${BASH_SOURCE[0]}")" >/dev/null && pwd)/$(basename "${BASH_SOURCE[0]}")
cd "$(git rev-parse --show-toplevel)"
OVH_PLATFORM=${OVH_PLATFORM:-$HOME/Developer/platform}

say() { printf '%s\n' "$*"; }
die() { printf 'x %s\n' "$*" >&2; exit 1; }

# Host configuration a merge cannot carry on its own: make ovh-install puts it on the host,
# and the merge deploys at once, so it would come up against the configuration before.
NEEDS_INSTALL='^deploy/ovh/(compose\.yaml|service\.env|[^/]*\.enc\.env|[^/]*\.caddyfile|systemd/)'

# A workflow that runs on pull requests, in each of the ways `on:` can say so: inline, as a
# key, or as an item of a list. A mention in an expression, github.event.pull_request, is none.
ON_PULL_REQUEST='^(on:.*[^a-z_]pull_request|[[:space:]]+pull_request(_target)?:|[[:space:]]*-[[:space:]]*pull_request(_target)?[[:space:]]*$)'

SCRATCH=""; WORK=""
cleanup() {
  if [ -n "$WORK" ]; then git worktree remove --force "$WORK" >/dev/null 2>&1 || true; fi
  if [ -n "$SCRATCH" ]; then rm -rf "$SCRATCH"; fi
}
trap cleanup EXIT

scratch() { # the directory this run writes into, made the first time it is asked for
  [ -n "$SCRATCH" ] || SCRATCH=$(mktemp -d "${TMPDIR:-/tmp}/ship.XXXXXX")
}

handover() { # handover <arg>...: the platform's main version of this script runs instead, where this copy is not it
  local main code=0
  if ! git -C "$OVH_PLATFORM" rev-parse --git-dir >/dev/null 2>&1; then
    say "> $OVH_PLATFORM is not here, so this repo's own scripts/ship.sh runs, and it may be behind the platform's"
    return 0
  fi
  scratch
  # The checkout's own origin/main may be days old, so it is fetched first; the ref it writes
  # is the one git fetch would, and nothing the checkout has out is touched.
  if ! git -C "$OVH_PLATFORM" fetch --quiet origin "+refs/heads/main:refs/remotes/origin/main" >/dev/null 2>&1 \
     || ! main=$(git -C "$OVH_PLATFORM" rev-parse --verify --quiet "refs/remotes/origin/main^{commit}") \
     || ! git -C "$OVH_PLATFORM" cat-file -p "$main:scripts/ship.sh" > "$SCRATCH/ship.sh" 2>/dev/null; then
    say "> the platform's main cannot be fetched into $OVH_PLATFORM, so this repo's own scripts/ship.sh runs, and it may be behind it"
    return 0
  fi
  cmp -s "$SELF" "$SCRATCH/ship.sh" && return 0
  say "> this repo's scripts/ship.sh differs from the platform's main, ${main:0:7}, which runs in its place"
  SHIP_FROM_PLATFORM=$main bash "$SCRATCH/ship.sh" "$@" || code=$?
  exit "$code"
}

checkout() { # checkout <sha>: the throwaway worktree, at that commit
  if [ -z "$WORK" ]; then
    scratch
    WORK=$SCRATCH/work
    git worktree add --quiet --detach "$WORK" "$1"
  else
    git -C "$WORK" checkout --quiet --detach "$1"
  fi
}

unsigned() { # unsigned <range>: each commit in it without a good signature, as "<sha> <%G?> <subject>"
  git --no-pager log --format='%h %G? %s' "$1" 2>/dev/null | awk '$2 != "G"'
}

has() { # has <sha> <path>: whether that commit holds the file
  git cat-file -e "$1:$2" 2>/dev/null
}

took() { # took <since>: the time from then to now, as 4m05s
  local s=$(( $(date +%s) - $1 ))
  if [ "$s" -ge 60 ]; then printf '%dm%02ds' $((s / 60)) $((s % 60)); else printf '%ds' "$s"; fi
}

gated_line() { # gated_line <sha> < text: the last make ci line in it for that commit
  local line found=""
  # A body typed into GitHub's page ends without a newline, and its last line counts too.
  while IFS= read -r line || [ -n "$line" ]; do
    if [[ $line == "\`make ci\`:"* && $line =~ \(([0-9a-f]{7,40})\), && $1 == "${BASH_REMATCH[1]}"* ]]; then
      found=$line
    fi
  done
  printf '%s' "$found"
}

released_as() { # released_as <sha>: the release tag origin has on that commit, or nothing
  # An annotated tag's line names the tag object; its peeled ^{} line names the commit.
  git ls-remote --tags origin 'v*' \
    | awk -v sha="$1" '$1 == sha { t = $2; sub("^refs/tags/", "", t); sub("\\^\\{\\}$", "", t); print t; exit }'
}

releases_merges() { # releases_merges: whether a push to main starts the new head's release.yml
  # The template's releases every merge; obsidian's is cut by hand or by a pushed tag, and
  # waiting for its run after a merge would wait for nothing.
  git cat-file -p "$NEW:.github/workflows/release.yml" 2>/dev/null | awk '
    /^on:/ { on = 1; next }
    on && /^[^[:space:]#]/ { on = 0 }
    on && /^  [a-z_]+:/ { push = ($0 ~ /^  push:/); list = 0; next }
    push && /^    branches:/ { list = 1; if ($0 ~ /[[, ]main[], ]/) found = 1; next }
    push && list && /^      - *main[[:space:]]*$/ { found = 1 }
    push && /^    [a-z_]+:/ { list = 0 }
    END { exit !found }'
}

run_of() { # run_of <workflow> <field> <value>: the run of that workflow for a commit or tag, once it has started
  local id=""
  for _ in $(seq 1 45); do
    id=$(gh run list -R "$REPO" --workflow "$1" --limit 10 --json databaseId,headSha,headBranch \
           --jq ".[] | select(.$2 == \"$3\") | .databaseId" | head -1)
    [ -n "$id" ] && break
    sleep 2
  done
  printf '%s' "$id"
}

head_on_github() { gh pr view "$PR" -R "$REPO" --json headRefOid --jq .headRefOid; }
state_on_github() { gh pr view "$PR" -R "$REPO" --json state --jq .state; }

checks() { # the checks of the pull request's workflows, on the head it has now
  local since ended bad
  since=$(date +%s)
  say "> its workflows' checks on ${NEW:0:7}"
  # Straight after a push GitHub has not reported the checks yet, and gh pr checks says so
  # with the status of a failure. They get a minute to appear before they are watched.
  for _ in $(seq 1 30); do
    [[ $(gh pr checks "$PR" -R "$REPO" 2>&1 || true) == *"no checks reported"* ]] || break
    sleep 2
  done
  [[ $(gh pr checks "$PR" -R "$REPO" 2>&1 || true) != *"no checks reported"* ]] \
    || die "#$PR: no checks reported a minute on, so nothing gates it: is CI running for it?"
  # The watch only waits. gh exits 0 for a check that was cancelled, as a job is at its
  # timeout-minutes, since it counts that as neither failed nor pending, so how each check
  # ended is read once the watch is over, and nothing but a pass, a skip or a neutral end lets
  # it in.
  gh pr checks "$PR" -R "$REPO" --watch --fail-fast >/dev/null 2>&1 || true
  ended=$(gh pr checks "$PR" -R "$REPO" --json name,state --jq '.[] | [.name, .state] | @tsv') \
    || die "#$PR: how its checks ended cannot be read, and main was left as it is: gh pr checks $PR"
  bad=$(awk -F'\t' '$2 != "SUCCESS" && $2 != "SKIPPED" && $2 != "NEUTRAL" {
          s = tolower($2); gsub("_", " ", s); print $1 ": " s }' <<<"$ended")
  if [ -n "$bad" ]; then
    while IFS= read -r line; do say "    $line" >&2; done <<<"$bad"
    die "#$PR: a check did not pass, and main was left as it is: gh pr checks $PR"
  fi
  say "> passed, after $(took "$since")"
}

gate_here() { # make ci on the new head, in the throwaway worktree, its line put into the body
  local line out body kept
  line=$(gh pr view "$PR" -R "$REPO" --json body --jq .body | gated_line "$NEW")
  if [ -n "$line" ]; then say "> make ci passed on ${NEW:0:7} already: its line is in the body"; return; fi
  git cat-file -p "$NEW:Makefile" 2>/dev/null | grep -qE '^ci:' \
    || die "no workflow runs on #$PR's pull requests and there is no make ci, so nothing would gate it"
  checkout "$NEW"
  say "> no workflow runs on its pull requests, so the gate is make ci, on ${NEW:0:7}:"
  out=$SCRATCH/gate.out
  # The gate names the branch from GATE_BRANCH, since the worktree it starts from is detached,
  # and merges origin's main, which the new head is on top of, rather than whatever the local
  # main happens to be.
  if ! (cd "$WORK" && GATE_BRANCH=$BRANCH BASE=origin/main make ci) 2>&1 | tee "$out"; then
    if [ -f "$WORK/build-gate.log" ]; then
      kept=${TMPDIR:-/tmp}/ship-$PR-build-gate.log
      cp "$WORK/build-gate.log" "$kept"
      say "> its full log is kept in $kept"
    fi
    die "make ci failed on ${NEW:0:7}, and main was left as it is"
  fi
  line=$(gated_line "$NEW" < "$out")
  [ -n "$line" ] || die "make ci passed but printed no line for ${NEW:0:7}, so the pull request cannot record it"
  # The new line replaces any line before it, which was for a commit that is not merged.
  body=$SCRATCH/body
  gh pr view "$PR" -R "$REPO" --json body --jq .body \
    | awk -v line="$line" '
        index($0, "`make ci`:") == 1 { next }
        NF { for (; blank > 0; blank--) print ""; print; any = 1; next }
        any { blank++ }
        END { if (any) print ""; print line }' > "$body"
  gh pr edit "$PR" -R "$REPO" --body-file "$body" >/dev/null
  say "> its line is in the body of #$PR"
}

follow() { # the release the merge starts, the deploy of its tag, production since
  local since=$1 release tag deploy service minutes level lines
  release=$(run_of release.yml headSha "$NEW")
  [ -n "$release" ] || die "no release run started for ${NEW:0:7}: gh run list --workflow release.yml"
  gh run watch "$release" -R "$REPO" --exit-status >/dev/null 2>&1 \
    || die "the release of ${NEW:0:7} stopped: gh run view $release --log-failed"
  tag=$(released_as "$NEW")
  [ -n "$tag" ] || die "the release run passed and ${NEW:0:7} has no tag: gh run view $release"
  say "> ${NEW:0:7} is $tag"
  TAG=$tag

  if has "$NEW" .github/workflows/deploy.yml; then
    deploy=$(run_of deploy.yml headBranch "$tag")
    [ -n "$deploy" ] || die "no deploy started for $tag: gh run list --workflow deploy.yml"
    gh run watch "$deploy" -R "$REPO" --exit-status >/dev/null 2>&1 \
      || die "the deploy of $tag failed; where it reached the host, the release before it is back: gh run view $deploy"
    say "> $tag is deployed"
  fi

  service=$(git cat-file -p "$NEW:deploy/ovh/service.env" 2>/dev/null | sed -n 's/^SERVICE=//p')
  [ -n "$service" ] || return 0
  if [ ! -f "$OVH_PLATFORM/Makefile" ]; then
    say "> $OVH_PLATFORM is not here, so production's logs are read by hand"
    return 0
  fi
  minutes=$(( ($(date +%s) - since) / 60 + 1 ))
  say "> $service in production, the last ${minutes}m:"
  for level in error warn; do
    if ! lines=$(make -s -C "$OVH_PLATFORM" logs SERVICE="$service" SINCE="${minutes}m" LEVEL=$level LIMIT=50 2>/dev/null); then
      say "  $level: could not be read: make -C $OVH_PLATFORM logs SERVICE=$service LEVEL=$level"
    elif [ -n "$lines" ]; then
      say "  $level:"
      while IFS= read -r line; do say "    ${line:0:200}"; done <<<"$lines"
    else
      say "  $level: none"
    fi
  done
}

ship() {
  local state base head files needs reasons why pushed
  [[ $PR =~ ^[0-9]+$ ]] || { echo "usage: make ship PR=<number>" >&2; exit 2; }
  REPO=$(gh repo view --json nameWithOwner --jq .nameWithOwner)

  say "[1/5] pull request #$PR"
  IFS=$'\t' read -r state base BRANCH head < <(gh pr view "$PR" -R "$REPO" \
    --json state,baseRefName,headRefName,headRefOid --jq '[.state, .baseRefName, .headRefName, .headRefOid] | @tsv') || true
  [ -n "${state:-}" ] || die "#$PR cannot be read from GitHub"
  [ "$state" = OPEN ] || die "#$PR is $(tr '[:upper:]' '[:lower:]' <<<"$state"), not open"
  [ "$base" = main ] || die "#$PR goes into $base, and only a pull request into main is shipped"
  git fetch --quiet origin "+refs/heads/main:refs/remotes/origin/main" "+refs/heads/$BRANCH:refs/remotes/origin/$BRANCH" \
    || die "origin cannot be reached, or has no branch $BRANCH"
  [ "$(git rev-parse "refs/remotes/origin/$BRANCH")" = "$head" ] \
    || die "origin's $BRANCH is not the head GitHub shows for #$PR: run it again in a moment"
  say "> $BRANCH (${head:0:7}) into main"
  files=$(git diff --name-only "origin/main...$head")
  # The release's note is what CHANGELOG.md gained, so a pull request without an entry goes
  # out with nothing to say about it.
  if has origin/main CHANGELOG.md && ! grep -qx CHANGELOG.md <<<"$files"; then
    die "#$PR adds no CHANGELOG.md entry, and that entry is what its release says"
  fi
  needs=$(grep -E "$NEEDS_INSTALL" <<<"$files" || true)
  if [ -n "$needs" ]; then
    say "> host configuration in it, which make ovh-install puts on the host:"
    while IFS= read -r line; do say "    $line"; done <<<"$needs"
    [ "${INSTALLED:-}" = 1 ] || die "run make ovh-install from its branch first, then INSTALLED=1 make ship PR=$PR"
  fi

  say "[2/5] onto main, signed"
  reasons=()
  git merge-base --is-ancestor origin/main "$head" || reasons+=("main has moved on")
  [ -z "$(git rev-list --merges "origin/main..$head")" ] || reasons+=("it holds a merge")
  [ -z "$(unsigned "origin/main..$head")" ] || reasons+=("it holds a commit with no good signature")
  NEW=$head
  if [ ${#reasons[@]} -gt 0 ]; then
    why=$(printf '%s, ' "${reasons[@]}"); why=${why%, }
    say "> rebased onto main and signed again, since $why"
    checkout "$head"
    # --force-rebase writes every commit again, which is what signs each one with my key.
    if ! git -C "$WORK" rebase --quiet --force-rebase --gpg-sign origin/main >/dev/null 2>&1; then
      git -C "$WORK" rebase --abort >/dev/null 2>&1 || true
      die "$BRANCH does not rebase onto main cleanly: rebase it by hand, resolve, push, then make ship PR=$PR again"
    fi
    NEW=$(git -C "$WORK" rev-parse HEAD)
  else
    say "> on top of main already, every commit signed"
  fi
  [ -n "$(git rev-list "origin/main..$NEW")" ] || die "#$PR has nothing main does not have already"
  [ -z "$(git rev-list --merges "origin/main..$NEW")" ] || die "a merge is left in $BRANCH after the rebase"
  bad=$(unsigned "origin/main..$NEW")
  if [ -n "$bad" ]; then
    while IFS= read -r line; do say "    $line" >&2; done <<<"$bad"
    die "these have no good signature by a key gpg.ssh.allowedSignersFile trusts, so main would show them unverified: nothing was pushed"
  fi
  git --no-pager log --reverse --format='    %h %s' "origin/main..$NEW"
  if [ "$NEW" != "$head" ]; then
    say "> force-pushing $BRANCH, a branch of mine alone, with a lease on ${head:0:7}: it is ${NEW:0:7} now"
    git push --quiet --force-with-lease="refs/heads/$BRANCH:$head" origin "$NEW:refs/heads/$BRANCH" \
      || die "origin's $BRANCH moved while this ran, so it was left as it is: make ship PR=$PR again"
    # GitHub moves the pull request to its new head a moment after the push.
    for _ in $(seq 1 30); do
      [ "$(head_on_github)" = "$NEW" ] && break
      sleep 2
    done
    [ "$(head_on_github)" = "$NEW" ] || die "GitHub shows #$PR at its old head a minute after the push: run it again"
  fi

  say "[3/5] the gate"
  if git grep -qE "$ON_PULL_REQUEST" "$NEW" -- '.github/workflows/*.yml' '.github/workflows/*.yaml'; then
    checks
  else
    gate_here
  fi

  say "[4/5] main"
  pushed=$(date +%s)
  # Never forced: when main has moved since the fetch, this is no fast-forward and origin says no.
  git push --quiet origin "$NEW:refs/heads/main" \
    || die "origin refused ${NEW:0:7} as main, as it does when main moved meanwhile; nothing was forced, and make ship PR=$PR again rebases onto the new main"
  for _ in $(seq 1 30); do
    state=$(state_on_github)
    [ "$state" = MERGED ] && break
    sleep 2
  done
  [ "$state" = MERGED ] || die "main is ${NEW:0:7}, but a minute on GitHub does not show #$PR merged: gh pr view $PR"
  say "> main is ${NEW:0:7}, and #$PR is merged"

  say "[5/5] the release"
  TAG=""
  if releases_merges; then
    follow "$pushed"
  elif has "$NEW" .github/workflows/release.yml; then
    say "> its release.yml does not run on a merge to main, so it is released by hand: gh workflow run release.yml"
  elif git cat-file -p "$NEW:Makefile" 2>/dev/null | grep -qE '^release:'; then
    say "> released by hand here: git switch main && git pull --ff-only, then make release"
  else
    say "> nothing is released on a merge here"
  fi
  say "#$PR is on main as ${NEW:0:7}${TAG:+, released as $TAG}. Here: git switch main && git pull --ff-only"
}

PR=${1:-}
# The version handed over runs as it is, and nothing it runs is told it was: make ci here runs
# this script's tests, which hand over themselves.
if [ -n "${SHIP_FROM_PLATFORM:-}" ]; then unset SHIP_FROM_PLATFORM; else handover "$@"; fi
ship
