-- 0005: workspace membership. The (workspace_id, user_id) primary key is the
-- exclusivity rule: one role per user per workspace, enforced by the index,
-- never by a SELECT-then-INSERT (CLAUDE.md rule 16's discipline).
-- Roles are Owner > Editor > Viewer; the capability table lives in
-- SV\Security::ROLE_CAPS and is the authority. The column is ENUM so MySQL
-- enforces the domain; the local SQLite bridge ignores ENUM, so the app
-- rejects an unknown role too (SV\Security::decide()).
-- Idempotent and re-runnable. {prefix} and {charset} are substituted by
-- SV\App\Migrations before this file runs.

CREATE TABLE IF NOT EXISTS {prefix}sv_workspace_members (
  workspace_id bigint(20) unsigned NOT NULL,
  user_id bigint(20) unsigned NOT NULL,
  role enum('Owner','Editor','Viewer') NOT NULL,
  invited_by bigint(20) unsigned NULL,
  accepted_at datetime NULL,
  created_at datetime NOT NULL,
  PRIMARY KEY (workspace_id, user_id),
  KEY user_id (user_id)
) ENGINE=InnoDB {charset};
