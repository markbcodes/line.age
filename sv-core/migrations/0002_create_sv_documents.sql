-- 0002: documents and their append-only versions — the manuscript store.
-- Scene prose lives ONLY here (rule 13: never in a VX field).
-- Idempotent and re-runnable. {prefix} and {charset} are substituted by
-- SV\App\Migrations before this file runs; keep it portable across SQLite
-- (local) and MariaDB/MySQL (hosted).

CREATE TABLE IF NOT EXISTS {prefix}sv_documents (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  scene_id bigint(20) unsigned NOT NULL,
  format varchar(20) NOT NULL DEFAULT 'tiptap',
  created_at datetime NOT NULL,
  PRIMARY KEY (id),
  KEY scene_id (scene_id)
) ENGINE=InnoDB {charset};

CREATE TABLE IF NOT EXISTS {prefix}sv_doc_versions (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  document_id bigint(20) unsigned NOT NULL,
  version int unsigned NOT NULL,
  content_json longtext NOT NULL,
  content_hash char(64) NOT NULL,
  schema_version smallint unsigned NOT NULL DEFAULT 1,
  word_count int unsigned NOT NULL DEFAULT 0,
  created_by bigint(20) unsigned NOT NULL DEFAULT 0,
  created_at datetime NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY doc_version (document_id, version),
  KEY doc_hash (document_id, content_hash)
) ENGINE=InnoDB {charset};
