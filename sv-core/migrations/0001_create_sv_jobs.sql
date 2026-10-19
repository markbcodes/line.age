-- 0001: the job table.
-- Idempotent and re-runnable. {prefix} and {charset} are substituted by
-- SV\App\Migrations before this file runs; keep it portable across SQLite
-- (local) and MariaDB/MySQL (hosted).

CREATE TABLE IF NOT EXISTS {prefix}sv_jobs (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  job_key varchar(191) NOT NULL,
  status varchar(20) NOT NULL DEFAULT 'pending',
  created_at datetime NOT NULL,
  PRIMARY KEY (id),
  KEY job_key (job_key)
) ENGINE=InnoDB {charset};
