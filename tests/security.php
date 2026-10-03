<?php
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/storage.php';
require_once __DIR__ . '/../includes/preview.php';
$root = sys_get_temp_dir() . '/ikusasa-tests-' . bin2hex(random_bytes(8));
putenv('UPLOAD_ROOT=' . $root);
$checks = 0;
function check(bool $condition, string $message): void {
    global $checks;
    if (!$condition) throw new RuntimeException($message);
    $checks++;
}
function rejected(callable $operation, string $message): void {
    try { $operation(); } catch (InvalidArgumentException | RuntimeException $error) { check(true, $message); return; }
    throw new RuntimeException($message);
}
function archive(string $name, array $entries): string {
    global $root;
    $path = $root . '/' . $name . '.zip'; $zip = new ZipArchive(); $zip->open($path, ZipArchive::CREATE);
    foreach ($entries as $key => $content) $zip->addFromString($key, $content);
    $zip->close(); return $path;
}
try {
    storageRoot();
    check(!isWithin('/tmp/site-other/index.html', '/tmp/site'), 'A neighbouring directory is rejected.');
    check(isWithin('/tmp/site/index.html', '/tmp/site'), 'A child file is accepted.');
    foreach (['uploads/projects/../config.php', '/uploads/projects/a.html', 'uploads/projects/.htaccess', 'uploads/projects/a/../../escape.txt', 'uploads/projects/a\\b.js'] as $key) {
        rejected(fn() => storagePath($key, false), 'Unsafe storage path rejected.');
    }
    $site = storagePath('uploads/projects/test/site', false); mkdir($site, 0700, true);
    extractProjectArchive(archive('valid', ['website/index.html' => '<h1>Hello</h1>', 'website/app.js' => 'console.log("hello")']), $site);
    $project = ['extracted_path' => 'uploads/projects/test/site/'];
    check(projectEntry($project) === 'uploads/projects/test/site/website/index.html', 'A nested index.html is found.');
    $key = 'uploads/projects/test/site/website/index.html';
    check(is_file(ownedProjectFile($project, $key)), 'Owned project file resolves.');
    mkdir($root . '/projects/test/site-other'); file_put_contents($root . '/projects/test/site-other/a.txt', 'other');
    rejected(fn() => ownedProjectFile($project, 'uploads/projects/test/site-other/a.txt'), 'Sibling project files cannot be edited.');
    rejected(fn() => ownedProjectFile(['extracted_path' => 'uploads/projects/missing/'], $key), 'A missing base directory is rejected.');
    rejected(fn() => extractProjectArchive(archive('php', ['shell.php' => '<?php echo 1;']), $site), 'PHP is rejected.');
    rejected(fn() => extractProjectArchive(archive('traversal', ['../escape.txt' => 'bad']), $site), 'ZIP traversal is rejected.');
    rejected(fn() => extractProjectArchive(archive('config', ['.htaccess' => 'bad']), $site), 'Hidden configuration files are rejected.');
    rejected(fn() => extractProjectArchive(archive('oversized', ['huge.txt' => str_repeat('x', MAX_PROJECT_FILE_BYTES + 1)]), $site), 'Oversized extracted files are rejected.');
    $many = []; for ($i = 0; $i <= MAX_ARCHIVE_ENTRIES; $i++) $many["file$i.txt"] = 'x';
    rejected(fn() => extractProjectArchive(archive('many', $many), $site), 'Too many archive entries are rejected.');
    $link = archive('link', ['link.txt' => '/etc/passwd']); $zip = new ZipArchive(); $zip->open($link);
    $zip->setExternalAttributesName('link.txt', ZipArchive::OPSYS_UNIX, 0120777 << 16); $zip->close();
    rejected(fn() => extractProjectArchive($link, $site), 'ZIP symbolic links are rejected.');
    $source = '<html><head><base href="https://example.com"><link rel="stylesheet" href="style.css"></head><body><h1>Hello</h1><img src="pixel.png"><script src="app.js"></script><script src="https://example.com/remote.js"></script><iframe src="https://example.com"></iframe></body></html>';
    file_put_contents(ownedProjectFile($project, $key), $source);
    file_put_contents($site . '/website/style.css', 'body { color: red; background:url(pixel.png); }');
    file_put_contents($site . '/website/pixel.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jY3sAAAAASUVORK5CYII='));
    $preview = buildProjectPreview($project, $key);
    check(str_contains($preview, 'Content-Security-Policy'), 'Preview has a restrictive CSP.');
    check(str_contains($preview, "connect-src 'none'"), 'Preview cannot call network APIs.');
    check(str_contains($preview, 'data:image/png;base64,'), 'Local images are bundled.');
    check(str_contains($preview, 'color: red'), 'Local CSS is bundled.');
    check(str_contains($preview, 'console.log("hello")'), 'Local JavaScript is bundled.');
    check(!str_contains($preview, 'remote.js') && !str_contains($preview, '<iframe') && !str_contains($preview, '<base'), 'External scripts, frames and base tags are removed.');
    $GLOBALS['preview_bytes'] = 0;
    rejected(fn() => reservePreviewBytes(16 * 1024 * 1024), 'Preview memory expansion is bounded.');
    check(validDate('2026-10-03') && !validDate('2026-02-30') && !validDate('" onfocus="bad'), 'Attendance dates are validated.');
    check(validPassword(str_repeat('a', 12)) && !validPassword('short') && !validPassword(str_repeat('a', 73)), 'Password length is enforced.');
    $_SESSION = []; check(strlen(csrfToken()) === 64 && str_contains(csrfField(), csrfToken()), 'CSRF tokens are generated and rendered.');
    $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
    check(!loginIsLimited('learner', 'test'), 'A new login is not blocked.');
    for ($i = 0; $i < 5; $i++) recordLoginFailure('learner', 'test');
    check(loginIsLimited('learner', 'test'), 'Repeated failed logins are limited.');
    clearAccountLoginFailures('learner', 'test');
    check(!loginIsLimited('learner', 'test'), 'A successful account login clears its counter.');
    $initialRevision = accountRevision('learner', 42);
    revokeAccount('learner', 42);
    check(accountRevision('learner', 42) !== $initialRevision, 'Deactivation revokes existing account sessions.');
    echo "$checks security and preview checks passed.\n";
} finally {
    if (is_dir($root)) {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($iterator as $item) $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        rmdir($root);
    }
}
