<?php
declare(strict_types=1);

const PROJECT_EXTENSIONS = ['html', 'htm', 'css', 'js', 'txt', 'json', 'png', 'jpg', 'jpeg', 'gif', 'webp', 'ico', 'woff', 'woff2', 'ttf'];
const EDITABLE_EXTENSIONS = ['html', 'htm', 'css', 'js', 'txt', 'json'];
const MAX_UPLOAD_BYTES = 20 * 1024 * 1024;
const MAX_PROJECT_FILE_BYTES = 10 * 1024 * 1024;
const MAX_EXTRACTED_BYTES = 50 * 1024 * 1024;
const MAX_ARCHIVE_ENTRIES = 200;

function storageRoot(): string {
    $root = getenv('UPLOAD_ROOT') ?: dirname(__DIR__, 2) . '/ikusasa-storage';
    if (!is_dir($root) && !mkdir($root, 0700, true) && !is_dir($root)) throw new RuntimeException('Upload storage is unavailable.');
    $root = realpath($root);
    $webRoot = realpath(dirname(__DIR__));
    if (!$root || $root === $webRoot || str_starts_with($root, $webRoot . DIRECTORY_SEPARATOR)) {
        throw new RuntimeException('Upload storage must be outside the website directory.');
    }
    return $root;
}

function safeStorageKey(string $key): string {
    if (!preg_match('#^uploads/(projects|content|profiles)/[a-zA-Z0-9 _()./-]+$#D', $key)) {
        throw new InvalidArgumentException('Invalid file path.');
    }
    foreach (explode('/', $key) as $part) {
        if ($part === '' || $part === '.' || $part === '..' || str_starts_with($part, '.')) throw new InvalidArgumentException('Invalid file path.');
    }
    return $key;
}

function isWithin(string $path, string $directory): bool {
    return $path !== '' && $directory !== '' && str_starts_with($path, rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR);
}

function storagePath(string $key, bool $mustExist = true): string {
    $key = safeStorageKey(rtrim($key, '/'));
    $root = storageRoot();
    $path = $root . '/' . substr($key, strlen('uploads/'));
    if ($mustExist) {
        $resolved = realpath($path);
        if (!$resolved || !isWithin($resolved, $root) || is_link($path)) throw new InvalidArgumentException('File not found.');
        return $resolved;
    }
    $parent = dirname($path);
    while (!file_exists($parent) && $parent !== $root) $parent = dirname($parent);
    $resolvedParent = realpath($parent);
    if (!$resolvedParent || ($resolvedParent !== $root && !isWithin($resolvedParent, $root))) throw new InvalidArgumentException('Invalid file path.');
    return $path;
}

function fileUrl(string $key, bool $download = false): string {
    return '/file.php?path=' . rawurlencode($key) . ($download ? '&download=1' : '');
}

function validateImage(string $path): void {
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($path);
    $image = @getimagesize($path);
    if (!in_array($mime, ['image/png', 'image/jpeg', 'image/gif', 'image/webp', 'image/x-icon', 'image/vnd.microsoft.icon'], true)
        || !$image || $image[0] > 10000 || $image[1] > 10000) {
        throw new InvalidArgumentException('Please upload a valid image, at most 10000 pixels per side.');
    }
}

function validateProjectFile(string $path, string $name): void {
    $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    if (!in_array($extension, PROJECT_EXTENSIONS, true) || filesize($path) > MAX_PROJECT_FILE_BYTES) {
        throw new InvalidArgumentException('Project contains an unsupported or oversized file.');
    }
    if (in_array($extension, ['png', 'jpg', 'jpeg', 'gif', 'webp', 'ico'], true)) validateImage($path);
    if (in_array($extension, EDITABLE_EXTENSIONS, true)) {
        $text = file_get_contents($path);
        if (str_contains($text, "\0") || !preg_match('//u', $text)) throw new InvalidArgumentException('Code files must contain UTF-8 text.');
    }
}

function removeStored(string $key): void {
    try { $path = storagePath($key); } catch (InvalidArgumentException $error) { return; }
    if (is_dir($path)) {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($iterator as $item) {
            if ($item->isLink()) { unlink($item->getPathname()); continue; }
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($path);
    } else {
        unlink($path);
    }
}

function extractProjectArchive(string $archive, string $destination): void {
    $zip = new ZipArchive();
    if ($zip->open($archive) !== true) throw new InvalidArgumentException('Unable to open this ZIP file.');
    try {
        if ($zip->numFiles > MAX_ARCHIVE_ENTRIES) throw new InvalidArgumentException('ZIP files may contain at most 200 entries.');
        $entries = []; $total = 0; $seen = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            if (!$stat) throw new InvalidArgumentException('Unable to read ZIP contents.');
            $name = $stat['name'];
            if (str_starts_with($name, '__MACOSX/') || basename($name) === '.DS_Store') continue;
            if (!preg_match('#^[a-zA-Z0-9 _()./-]+$#D', $name) || str_starts_with($name, '/')) throw new InvalidArgumentException('ZIP contains an invalid path.');
            foreach (explode('/', rtrim($name, '/')) as $part) {
                if ($part === '' || $part === '.' || $part === '..' || str_starts_with($part, '.')) throw new InvalidArgumentException('ZIP contains an unsafe path.');
            }
            if (!$zip->getExternalAttributesIndex($i, $opsys, $attributes)) throw new InvalidArgumentException('Unable to validate ZIP file attributes.');
            $kind = ($attributes >> 16) & 0170000;
            if ($opsys === ZipArchive::OPSYS_UNIX && $kind !== 0 && $kind !== 0100000 && $kind !== 0040000) {
                throw new InvalidArgumentException('ZIP links and special files are not allowed.');
            }
            if (str_ends_with($name, '/')) continue;
            if (isset($seen[$name]) || !in_array(strtolower(pathinfo($name, PATHINFO_EXTENSION)), PROJECT_EXTENSIONS, true)
                || ($stat['encryption_method'] ?? 0) !== 0 || $stat['size'] > MAX_PROJECT_FILE_BYTES) {
                throw new InvalidArgumentException('ZIP contains duplicate, encrypted, unsupported or oversized files.');
            }
            $total += $stat['size'];
            if ($total > MAX_EXTRACTED_BYTES) throw new InvalidArgumentException('Extracted ZIP contents may not exceed 50 MB.');
            $seen[$name] = true; $entries[] = [$name, $stat['size']];
        }
        if (!$entries) throw new InvalidArgumentException('ZIP contains no supported project files.');
        foreach ($entries as [$name, $expectedSize]) {
            $target = $destination . '/' . $name;
            if (!is_dir(dirname($target)) && !mkdir(dirname($target), 0700, true)) throw new RuntimeException('Unable to create project folder.');
            $input = $zip->getStream($name); $output = fopen($target, 'xb');
            if (!$input || !$output) throw new RuntimeException('Unable to extract project file.');
            try { $bytes = stream_copy_to_stream($input, $output, MAX_PROJECT_FILE_BYTES + 1); }
            finally { fclose($input); fclose($output); }
            if ($bytes !== $expectedSize) throw new InvalidArgumentException('ZIP contains invalid file sizes.');
            validateProjectFile($target, $name);
        }
    } finally { $zip->close(); }
}

function storeUpload(array $file, string $type): array {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) throw new InvalidArgumentException('Upload failed. Please choose a file up to 20 MB and try again.');
    if (!is_uploaded_file($file['tmp_name']) || filesize($file['tmp_name']) > MAX_UPLOAD_BYTES) throw new InvalidArgumentException('Upload must be at most 20 MB.');
    $original = basename($file['name']);
    $name = preg_replace('/[^a-zA-Z0-9._-]/', '_', $original);
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    $allowed = match ($type) {
        'projects' => [...PROJECT_EXTENSIONS, 'zip'],
        'profiles' => ['png', 'jpg', 'jpeg', 'gif', 'webp'],
        'content' => ['pdf', 'txt', 'csv', 'doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx', 'png', 'jpg', 'jpeg', 'gif', 'webp', 'mp4', 'webm'],
        default => [],
    };
    if (!in_array($ext, $allowed, true)) throw new InvalidArgumentException('This file type is not supported.');
    if ($type === 'profiles' || in_array($ext, ['png', 'jpg', 'jpeg', 'gif', 'webp'], true)) validateImage($file['tmp_name']);
    $id = bin2hex(random_bytes(16));
    $key = 'uploads/' . $type . '/' . $id . '/' . $name;
    $target = storagePath($key, false);
    if (!mkdir(dirname($target), 0700, true)) throw new RuntimeException('Unable to create upload folder.');
    $directoryKey = 'uploads/' . $type . '/' . $id;
    try {
        if (!move_uploaded_file($file['tmp_name'], $target)) throw new RuntimeException('Unable to store this file.');
        $extracted = null;
        if ($type === 'projects') {
            $extracted = $directoryKey . '/site/';
            $site = storagePath($directoryKey . '/site', false);
            if (!mkdir($site, 0700)) throw new RuntimeException('Unable to create project folder.');
            if ($ext === 'zip') extractProjectArchive($target, $site);
            else { validateProjectFile($target, $name); if (!copy($target, $site . '/' . $name)) throw new RuntimeException('Unable to store project file.'); }
        }
        return ['file_path' => $key, 'extracted_path' => $extracted, 'cleanup_key' => $directoryKey];
    } catch (Throwable $error) {
        removeStored($directoryKey);
        throw $error;
    }
}

function ownedProjectFile(array $project, string $key): string {
    if (empty($project['extracted_path'])) throw new InvalidArgumentException('This project has no editable files.');
    $base = storagePath($project['extracted_path']);
    $target = storagePath($key);
    if (!is_file($target) || !isWithin($target, $base)) throw new InvalidArgumentException('This file does not belong to your project.');
    return $target;
}

function projectFiles(array $project): array {
    if (empty($project['extracted_path'])) return [];
    try { $base = storagePath($project['extracted_path']); } catch (InvalidArgumentException $error) { return []; }
    $result = [];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
        if ($file->isFile() && !$file->isLink()) $result[] = rtrim($project['extracted_path'], '/') . '/' . substr($file->getPathname(), strlen($base) + 1);
    }
    sort($result);
    return $result;
}

function projectEntry(array $project): ?string {
    $files = array_filter(projectFiles($project), fn($key) => in_array(strtolower(pathinfo($key, PATHINFO_EXTENSION)), ['html', 'htm'], true));
    usort($files, fn($a, $b) => [(int) (strtolower(basename($a)) !== 'index.html'), substr_count($a, '/'), $a] <=> [(int) (strtolower(basename($b)) !== 'index.html'), substr_count($b, '/'), $b]);
    return $files[0] ?? null;
}
