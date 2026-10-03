<?php
// CLI only: copy verified files from a backup of the old uploads directory.
// Database paths remain unchanged. No source files are deleted or executed.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../includes/storage.php';
$source = realpath($argv[1] ?? '');
if (!$source || !is_dir($source) || $source === realpath(dirname(__DIR__))) { fwrite(STDERR, "Usage: php scripts/migrate-uploads.php /absolute/path/to/old/uploads\n"); exit(1); }
$count = 0; $skipped = 0;
foreach (['projects', 'content', 'profiles'] as $type) {
    $directory = $source . '/' . $type;
    if (!is_dir($directory) || is_link($directory)) continue;
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
        if (!$file->isFile() || $file->isLink()) continue;
        $key = 'uploads/' . $type . '/' . substr($file->getPathname(), strlen($directory) + 1);
        try {
            if (!isWithin(realpath($file->getPathname()), $directory)) throw new InvalidArgumentException('Outside source folder.');
            $target = storagePath($key, false);
            if ($type === 'projects' && strtolower($file->getExtension()) !== 'zip') validateProjectFile($file->getPathname(), $file->getFilename());
            if ($type === 'profiles') validateImage($file->getPathname());
            if (file_exists($target)) {
                if (hash_file('sha256', $target) !== hash_file('sha256', $file->getPathname())) throw new InvalidArgumentException('Destination already differs.');
                continue;
            }
            if (!is_dir(dirname($target))) mkdir(dirname($target), 0700, true);
            if (!copy($file->getPathname(), $target)) throw new RuntimeException('Copy failed.');
            $count++;
        } catch (Throwable $error) { $skipped++; fwrite(STDERR, "Skipped an unsafe, unsupported or conflicting file.\n"); }
    }
}
echo "Copied $count files; skipped $skipped. Original files and database records were preserved.\n";
exit($skipped ? 2 : 0);
