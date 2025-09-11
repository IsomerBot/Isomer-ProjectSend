#!/usr/bin/env bash
set -euo pipefail

# Make ALTERs idempotent in all upgrade scripts to prevent “duplicate column/key” fatals.
shopt -s nullglob
for f in /var/www/html/includes/upgrades/*.php; do
  # Only patch once
  grep -q "__PATCHED_IDEMPOTENT__" "$f" && continue

  sed -i -E '
    s/(ADD[[:space:]]+COLUMN)[[:space:]]+`/\1 IF NOT EXISTS ` /Ig;
    s/(DROP[[:space:]]+COLUMN)[[:space:]]+`/\1 IF EXISTS ` /Ig;
    s/(ADD[[:space:]]+UNIQUE[[:space:]]+KEY)[[:space:]]+`/\1 IF NOT EXISTS ` /Ig;
    s/(ADD[[:space:]]+UNIQUE[[:space:]]+INDEX)[[:space:]]+`/\1 IF NOT EXISTS ` /Ig;
    s/(ADD[[:space:]]+KEY)[[:space:]]+`/\1 IF NOT EXISTS ` /Ig;
    s/(ADD[[:space:]]+INDEX)[[:space:]]+`/\1 IF NOT EXISTS ` /Ig;
    s/(DROP[[:space:]]+FOREIGN[[:space:]]+KEY)[[:space:]]+`/\1 IF EXISTS ` /Ig;
  ' "$f"

  # Mark as patched
  printf "<?php /* __PATCHED_IDEMPOTENT__ */ ?>\n" >> "$f"
done

# Special case (seen in your logs): 2022102701 drops columns unguarded
F=/var/www/html/includes/upgrades/2022102701.php
if [ -f "$F" ]; then
  sed -i -E '
    s/` DROP COLUMN `client_id`/` DROP COLUMN IF EXISTS `client_id`/I;
    s/` DROP COLUMN `group_id`/` DROP COLUMN IF EXISTS `group_id`/I;
  ' "$F" || true
fi
