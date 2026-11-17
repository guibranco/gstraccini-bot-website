#!/usr/bin/env bash
#
# GStraccini-bot-website – pull request enrichment queue loop.
#
# Run from cPanel cron every minute:
#   * * * * * /bin/bash $HOME/public_html/worker/loop_requests.sh >> $HOME/logs/gstraccini-pr-queue.log 2>&1
#
# For ~55 s it checks the CloudAMQP queue size every few seconds through the
# management HTTP API (cheap, no PHP start-up). When messages are waiting it
# runs process-pr-queue.php, which drains the queue until it is idle and exits.
# A lock guarantees a single loop (and so a single worker) at a time.

set -u

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ENV_FILE="$SCRIPT_DIR/queue.secrets.env"
DATA_DIR="$SCRIPT_DIR/data"

QUEUE_NAME="${QUEUE_NAME:-gstraccini-website-pr-enrichment}"
PHP_BIN="${PHP_BIN:-php}"            # e.g. /opt/cpanel/ea-php84/root/usr/bin/php
LOOP_SECONDS="${LOOP_SECONDS:-55}"
CHECK_INTERVAL="${CHECK_INTERVAL:-5}"
IDLE_SECONDS="${IDLE_SECONDS:-30}"

log() { printf '[%s] %s\n' "$(date '+%Y-%m-%dT%H:%M:%S%z')" "$*"; }

if [ ! -f "$ENV_FILE" ]; then
    log "missing $ENV_FILE"
    exit 1
fi
# shellcheck disable=SC1090
. "$ENV_FILE"   # provides RABBITMQ_URL

mkdir -p "$DATA_DIR"
chmod 700 "$DATA_DIR"

# ── single instance ───────────────────────────────────────────────────────────
LOCK_FILE="$DATA_DIR/loop_requests.lock"
if command -v flock >/dev/null 2>&1; then
    exec 9>"$LOCK_FILE"
    flock -n 9 || exit 0
else
    LOCK_DIR="$LOCK_FILE.d"
    if ! mkdir "$LOCK_DIR" 2>/dev/null; then
        # stale lock (older than 10 minutes) → take over
        if [ -n "$(find "$LOCK_DIR" -maxdepth 0 -mmin +10 2>/dev/null)" ]; then
            rm -rf "$LOCK_DIR" && mkdir "$LOCK_DIR" || exit 0
        else
            exit 0
        fi
    fi
    trap 'rm -rf "$LOCK_DIR"' EXIT
fi

# ── parse amqp(s)://user:pass@host[:port]/vhost ───────────────────────────────
rest="${RABBITMQ_URL#*://}"
creds="${rest%%@*}"
hostpart="${rest#*@}"
host="${hostpart%%/*}"
host="${host%%:*}"
vhost="${hostpart#*/}"
[ "$vhost" = "$hostpart" ] && vhost=""
[ -z "$vhost" ] && vhost="%2F"
user="${creds%%:*}"
pass="${creds#*:}"

# Queue depth via the CloudAMQP management API. Credentials go through stdin
# (-K -) so they never show up in the shared host's process list.
queue_size() {
    local body count
    body="$(printf 'user = "%s:%s"\n' "$user" "$pass" | curl -sS -f --max-time 10 -K - \
        "https://$host/api/queues/$vhost/$QUEUE_NAME?columns=messages" 2>/dev/null)" || { echo 0; return; }
    count="$(printf '%s' "$body" | grep -o '"messages":[0-9]*' | head -n1 | cut -d: -f2)"
    echo "${count:-0}"
}

end=$((SECONDS + LOOP_SECONDS))
while [ "$SECONDS" -lt "$end" ]; do
    size="$(queue_size)"
    if [ "$size" -gt 0 ] 2>/dev/null; then
        log "queue has $size message(s), starting worker"
        "$PHP_BIN" "$SCRIPT_DIR/process-pr-queue.php" "$IDLE_SECONDS"
        log "worker exited with $?"
    fi
    sleep "$CHECK_INTERVAL"
done
