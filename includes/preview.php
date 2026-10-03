<?php
require_once __DIR__ . '/storage.php';

function reservePreviewBytes(int $bytes): void {
    $GLOBALS['preview_bytes'] = ($GLOBALS['preview_bytes'] ?? 0) + $bytes;
    if ($GLOBALS['preview_bytes'] > 15 * 1024 * 1024) throw new InvalidArgumentException('Preview is too large. Reduce the page or image sizes.');
}

function previewAsset(array $project, string $fromKey, string $reference): ?string {
    // Only project-local paths are bundled. No network requests are made by the server.
    if ($reference === '' || str_starts_with($reference, '#') || preg_match('#^[a-z][a-z0-9+.-]*:|^//#i', $reference)) return null;
    $reference = rawurldecode(explode('?', explode('#', $reference)[0])[0]);
    if (str_starts_with($reference, '/')) $parts = explode('/', rtrim($project['extracted_path'], '/') . '/' . ltrim($reference, '/'));
    else $parts = explode('/', dirname($fromKey) . '/' . $reference);
    $normal = [];
    foreach ($parts as $part) {
        if ($part === '' || $part === '.') continue;
        if ($part === '..') array_pop($normal); else $normal[] = $part;
    }
    $key = implode('/', $normal);
    try { ownedProjectFile($project, $key); return $key; } catch (InvalidArgumentException $error) { return null; }
}

function previewDataUrl(array $project, string $fromKey, string $reference): string {
    $key = previewAsset($project, $fromKey, $reference);
    if (!$key) return '';
    $extension = strtolower(pathinfo($key, PATHINFO_EXTENSION));
    if (!in_array($extension, ['png', 'jpg', 'jpeg', 'gif', 'webp', 'ico', 'woff', 'woff2', 'ttf'], true)) return '';
    $path = ownedProjectFile($project, $key);
    if (filesize($path) > 2 * 1024 * 1024) return '';
    $mime = match ($extension) {
        'woff' => 'font/woff', 'woff2' => 'font/woff2', 'ttf' => 'font/ttf',
        default => (new finfo(FILEINFO_MIME_TYPE))->file($path),
    };
    reservePreviewBytes((int) ceil(filesize($path) * 4 / 3) + 100);
    return 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($path));
}

function previewCss(array $project, string $key, string $css): string {
    $css = preg_replace('/@import\s+[^;]+;/i', '', $css);
    return preg_replace_callback('/url\(\s*[\'"]?([^\)\'"\s]+)[\'"]?\s*\)/i',
        fn($match) => 'url("' . previewDataUrl($project, $key, $match[1]) . '")', $css);
}

function buildProjectPreview(array $project, string $key): string {
    $path = ownedProjectFile($project, $key);
    if (!in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), ['html', 'htm'], true)) throw new InvalidArgumentException('Choose an HTML file to preview.');
    $GLOBALS['preview_bytes'] = 0;
    reservePreviewBytes(filesize($path));
    $document = new DOMDocument();
    $previous = libxml_use_internal_errors(true);
    try { $document->loadHTML('<?xml encoding="utf-8" ?>' . file_get_contents($path), LIBXML_NONET); }
    finally { libxml_clear_errors(); libxml_use_internal_errors($previous); }
    $xpath = new DOMXPath($document);
    foreach (iterator_to_array($xpath->query('//base|//meta|//iframe|//object|//embed')) as $node) $node->parentNode->removeChild($node);
    foreach (iterator_to_array($xpath->query('//link')) as $node) {
        $asset = previewAsset($project, $key, $node->getAttribute('href'));
        if (strtolower($node->getAttribute('rel')) === 'stylesheet' && $asset && strtolower(pathinfo($asset, PATHINFO_EXTENSION)) === 'css') {
            reservePreviewBytes(filesize(ownedProjectFile($project, $asset)));
            $style = $document->createElement('style');
            $style->appendChild($document->createTextNode(previewCss($project, $asset, file_get_contents(ownedProjectFile($project, $asset)))));
            $node->parentNode->replaceChild($style, $node);
        } else $node->parentNode->removeChild($node);
    }
    foreach (iterator_to_array($xpath->query('//script[@src]')) as $node) {
        $asset = previewAsset($project, $key, $node->getAttribute('src'));
        if ($asset && strtolower(pathinfo($asset, PATHINFO_EXTENSION)) === 'js') {
            reservePreviewBytes(filesize(ownedProjectFile($project, $asset)));
            $node->removeAttribute('src');
            $node->appendChild($document->createTextNode(file_get_contents(ownedProjectFile($project, $asset))));
        } else $node->parentNode->removeChild($node);
    }
    foreach ($xpath->query('//style') as $node) {
        // External styles were already rewritten relative to their own file.
        if (!str_contains($node->textContent, 'data:')) $node->nodeValue = previewCss($project, $key, $node->textContent);
    }
    foreach ($xpath->query('//*[@style]') as $node) $node->setAttribute('style', previewCss($project, $key, $node->getAttribute('style')));
    foreach ($xpath->query('//img|//source|//video|//audio') as $node) {
        if ($node->hasAttribute('src')) $node->setAttribute('src', previewDataUrl($project, $key, $node->getAttribute('src')));
        if ($node->hasAttribute('poster')) $node->setAttribute('poster', previewDataUrl($project, $key, $node->getAttribute('poster')));
        $node->removeAttribute('srcset');
    }
    $head = $document->getElementsByTagName('head')->item(0);
    if (!$head) { $head = $document->createElement('head'); $document->documentElement->insertBefore($head, $document->documentElement->firstChild); }
    $meta = $document->createElement('meta');
    $meta->setAttribute('http-equiv', 'Content-Security-Policy');
    $meta->setAttribute('content', "default-src 'none'; script-src 'unsafe-inline'; style-src 'unsafe-inline'; img-src data:; font-src data:; connect-src 'none'; frame-src 'none'; object-src 'none'; form-action 'none'; base-uri 'none'");
    $head->insertBefore($meta, $head->firstChild);
    $charset = $document->createElement('meta'); $charset->setAttribute('charset', 'utf-8');
    $head->insertBefore($charset, $meta);
    $html = $document->saveHTML();
    if (strlen($html) > 15 * 1024 * 1024) throw new InvalidArgumentException('Preview is too large. Reduce the page or image sizes.');
    return $html;
}
