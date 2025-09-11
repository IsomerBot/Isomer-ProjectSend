#!/usr/bin/env bash
set -euo pipefail

DBPHP=/var/www/html/install/database.php

# If installer didn’t include these tables, append minimal CREATEs so upgrades won’t crash.
grep -q "TABLE_DISCIPLINE" "$DBPHP" || cat >> "$DBPHP" <<'PHPADD'

/* Added by build patch: ensure required tables exist */
function __patch_create_extra_tables($dbh) {
    $dbh->query("CREATE TABLE IF NOT EXISTS `".TABLE_DISCIPLINE."` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `name` varchar(255) NOT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uniq_name` (`name`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $dbh->query("CREATE TABLE IF NOT EXISTS `".TABLE_DELIVERABLE_TYPE."` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `name` varchar(255) NOT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uniq_name` (`name`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

PHPADD

# Wire it into base install query builder if not already
grep -q "__patch_create_extra_tables" "$DBPHP" || \
  sed -i 's/return \$queries;/__patch_create_extra_tables($dbh);\n    return $queries;/' "$DBPHP" || true
