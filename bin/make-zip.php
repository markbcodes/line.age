<?php
/**
 * bin/make-zip.php <source-dir> <zip-path> [exclude-dirs]
 *
 * Zips a directory with ZipArchive — used by bin/build.sh because Git Bash
 * on Windows ships `unzip` but not `zip`. Excludes comma-separated dir names
 * at any depth (client-src, node_modules, tests, .git, ...). Always excludes
 * the zip's own target if nested.
 */

declare(strict_types=1);

if (php_sapi_name() !== 'cli') {
	exit(1);
}

$source = $argv[1] ?? '';
$zipPath = $argv[2] ?? '';
$exclude = array_filter(array_map('trim', explode(',', $argv[3] ?? '')));

if ($source === '' || $zipPath === '') {
	fwrite(STDERR, "Usage: make-zip.php <source-dir> <zip-path> [exclude-dirs]\n");
	exit(1);
}

$sourceReal = realpath($source);
if ($sourceReal === false || ! is_dir($sourceReal)) {
	fwrite(STDERR, "make-zip.php: source dir not found: {$source}\n");
	exit(1);
}

@mkdir(dirname($zipPath), 0777, true);

$zip = new ZipArchive();
if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
	fwrite(STDERR, "make-zip.php: cannot open {$zipPath}\n");
	exit(1);
}

$rootName = basename($sourceReal);
$iterator = new RecursiveIteratorIterator(
	new RecursiveDirectoryIterator($sourceReal, FilesystemIterator::SKIP_DOTS),
	RecursiveIteratorIterator::LEAVES_ONLY
);

$added = 0;
foreach ($iterator as $file) {
	/** @var SplFileInfo $file */
	$relative = substr($file->getPathname(), strlen($sourceReal) + 1);
	$parts = explode(DIRECTORY_SEPARATOR, $relative);
	foreach ($parts as $part) {
		if (in_array($part, $exclude, true)) {
			continue 2;
		}
	}
	if (! $file->isFile()) {
		continue;
	}
	$zip->addFile($file->getPathname(), $rootName . '/' . str_replace(DIRECTORY_SEPARATOR, '/', $relative));
	$added++;
}

$zip->close();
echo "make-zip.php: {$added} files -> {$zipPath}\n";
