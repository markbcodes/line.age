-- 0003: the audit log (CLAUDE.md rule 14). Every custom mutating route writes
-- its own row here; VX's own writes log themselves and do not use this table.
-- before_hash/after_hash avoid the reserved words `before`/`after`, so the DDL
-- stays portable across SQLite and MariaDB/MySQL.
-- Idempotent and re-runnable. {prefix} and {charset} are substituted by
-- SV\App\Migrations before this file runs.

CREATE TABLE IF NOT EXISTS {prefix}sv_audit_log (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  workspace_id bigint(20) unsigned NULL,
  actor_id bigint(20) unsigned NOT NULL DEFAULT 0,
  action varchar(64) NOT NULL,
  entity varchar(191) NOT NULL DEFAULT '',
  before_hash char(64) NULL,
  after_hash char(64) NULL,
  at datetime NOT NULL,
  PRIMARY KEY (id),
  KEY actor_id (actor_id),
  KEY entity (entity)
) ENGINE=InnoDB {charset};
