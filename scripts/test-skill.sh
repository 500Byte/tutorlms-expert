#!/usr/bin/env bash
# A/B test this skill with the runtime the user actually has: opencode.
# Baseline = prompt in an empty sandbox with SKILL.md hidden and tools forbidden.
# Treatment = same prompt in the skill repo, skill available.
# Writes only to $OUT (default /tmp/tutor-evals); never edits repo content.
#
# Usage:
#   ./scripts/test-skill.sh                 # all scenarios, 1 rep
#   ./scripts/test-skill.sh rest-auth       # one scenario (filename substring)
#   REPS=3 ./scripts/test-skill.sh          # variance check
#   MODEL=opencode-go/<model> PURE=0 ./scripts/test-skill.sh --judge
set -euo pipefail

SKILL_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SKILL_FILE="$SKILL_DIR/SKILL.md"
HIDDEN="$SKILL_FILE.baseline-off"
MODEL="${MODEL:-opencode-go/deepseek-v4.1-flash}"
PURE="${PURE:-1}"
OUT="${OUT:-/tmp/tutor-evals}"
SANDBOX="$OUT/sandbox"
REPS="${REPS:-1}"
JUDGE=0
FILTER=""

for arg in "$@"; do
  case "$arg" in
    --judge) JUDGE=1 ;;
    -*) echo "unknown flag: $arg" >&2; exit 2 ;;
    *) FILTER="$arg" ;;
  esac
done

command -v opencode >/dev/null || { echo "opencode not on PATH" >&2; exit 2; }
command -v python3 >/dev/null || { echo "python3 not on PATH" >&2; exit 2; }
mkdir -p "$OUT"
rm -rf "$SANDBOX"
mkdir -p "$SANDBOX"
[ -z "$(ls -A "$SANDBOX")" ] || { echo "sandbox is not empty: $SANDBOX" >&2; exit 2; }

PURE_FLAG=()
[ "$PURE" = "1" ] && PURE_FLAG=(--pure)

log() { printf '%s %s\n' "$(date +%H:%M:%S)" "$*"; }

restore_skill() {
  if [ -f "$HIDDEN" ] && [ ! -f "$SKILL_FILE" ]; then
    mv "$HIDDEN" "$SKILL_FILE"
    echo "[restore] SKILL.md restored" >&2
  fi
}
trap restore_skill EXIT INT TERM

skill_visible() {
  opencode debug skill 2>/dev/null | grep -q '"name": "tutorlms-expert"'
}

extract_text() {
  python3 - "$1" <<'PY'
import json, sys
out = []
for line in open(sys.argv[1], encoding="utf-8", errors="ignore"):
    line = line.strip()
    if not line:
        continue
    try:
        event = json.loads(line)
    except json.JSONDecodeError:
        continue
    part = event.get("part") or {}
    if event.get("type") == "text" and part.get("text"):
        out.append(part["text"])
print("".join(out).strip())
PY
}

run_arm() {
  local label="$1" prompt="$2" base="$3" rep="$4"
  local raw="$OUT/${base}.${label}.${rep}.json"
  local txt="$OUT/${base}.${label}.${rep}.txt"
  local dir timeout_s start dur rc
  if [ "$label" = "baseline" ]; then
    dir="$SANDBOX"
    timeout_s=180
    prompt="Answer from your own knowledge only. Do not use tools, do not read files, do not search the web. If you do not know a detail, say unknown. Keep it under 500 words.

$prompt"
  else
    dir="$SKILL_DIR"
    timeout_s=240
  fi
  start=$(date +%s)
  set +e
  ( cd "$dir" && timeout "$timeout_s" opencode run "${PURE_FLAG[@]}" -m "$MODEL" --format json "$prompt" ) > "$raw" 2>"$OUT/${base}.${label}.${rep}.err"
  rc=$?
  set -e
  dur=$(( $(date +%s) - start ))
  extract_text "$raw" > "$txt"
  log "  $label rep$rep: ${dur}s rc=$rc $(wc -c < "$txt" | tr -d ' ') bytes"
}

scenario_task() {
  python3 - "$1" <<'PY'
import sys
text = open(sys.argv[1], encoding="utf-8").read().split("---")
fm = text[1] if len(text) > 2 else ""
for line in fm.splitlines():
    if line.startswith("task:"):
        print(line.split(":", 1)[1].strip())
        break
PY
}

scenario_assertions() {
  python3 - "$1" <<'PY'
import sys
fm = open(sys.argv[1], encoding="utf-8").read().split("---")[1]
out, inside = [], False
for line in fm.splitlines():
    if line.startswith("assertions:"):
        inside = True
        continue
    if inside and line.startswith("runs:"):
        break
    if inside and line.strip().startswith("- "):
        out.append(line.strip()[2:].strip("'"))
print("\n".join(out))
PY
}

log "== static gates =="
python3 "$HOME/.agents/skills/skillforge/scripts/validate_skill.py" "$SKILL_DIR" 2>&1 | tail -2
python3 "$HOME/.agents/skills/skillforge/scripts/run_skill_evals.py" "$SKILL_DIR" --static 2>&1 | tail -2

log "== A/B with $MODEL (reps=$REPS, pure=$PURE) =="
for scenario in "$SKILL_DIR"/evals/scenarios/*.md; do
  base="$(basename "$scenario" .md)"
  case "$base" in *"$FILTER"*) ;; *) continue ;; esac
  task="$(scenario_task "$scenario")"
  [ -n "$task" ] || { log "skip $base: no task"; continue; }
  log "-- $base"

  for rep in $(seq 1 "$REPS"); do
    mv "$SKILL_FILE" "$HIDDEN"
    if skill_visible; then
      log "baseline still sees the skill; aborting"
      restore_skill
      exit 3
    fi
    run_arm baseline "$task" "$base" "$rep"
    restore_skill
    [ -f "$SKILL_FILE" ] || { log "SKILL.md missing after baseline"; exit 4; }
    run_arm treatment "$task" "$base" "$rep"
  done

  if [ "$JUDGE" -eq 1 ]; then
    assertions="$(scenario_assertions "$scenario")"
    judge_prompt="You are grading an A/B test. TASK: $task

ASSERTIONS:
$assertions

BASELINE OUTPUT:
$(cat "$OUT/${base}.baseline.${REPS}.txt")

TREATMENT OUTPUT:
$(cat "$OUT/${base}.treatment.${REPS}.txt")

Return ONLY JSON: {\"treatment_pass\": true|false, \"failed_assertions\": [\"...\"], \"reason\": \"one line\"}"
    start=$(date +%s)
    ( cd "$SKILL_DIR" && timeout 240 opencode run "${PURE_FLAG[@]}" -m "$MODEL" --format json "$judge_prompt" ) > "$OUT/${base}.judge.json" 2>/dev/null || true
    extract_text "$OUT/${base}.judge.json" > "$OUT/${base}.judge.txt"
    log "  judge: $(cat "$OUT/${base}.judge.txt") ($(( $(date +%s) - start ))s)"
  fi
done

log "outputs: $OUT"
