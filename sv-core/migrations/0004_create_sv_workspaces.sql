-- 0004: workspaces — the tenancy root (CLAUDE.md rule 3). A book belongs to
-- exactly one workspace through its VX `workspace` field; membership in
-- {prefix}sv_workspace_members is the only thing that grants access to it.
-- Nothing else in the schema carries tenancy, so this is the single boundary.
-- Idempotent and re-runnable. {prefix} and {charset} are substituted by
-- SV\App\Migrations before this file runs; keep it portable across SQLite
-- (local) and MariaDB/MySQL (hosted).

CREATE TABLE IF NOT EXISTS {prefix}sv_workspaces (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  universe_id bigint(20) unsigned NULL,
  name varchar(191) NOT NULL DEFAULT '',
  owner_id bigint(20) unsigned NOT NULL DEFAULT 0,
  created_at datetime NOT NULL,
  PRIMARY KEY (id),
  KEY owner_id (owner_id)
) ENGINE=InnoDB {charset};
