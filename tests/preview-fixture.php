<?php
require_once __DIR__ . '/../includes/storage.php';
require_once __DIR__ . '/../includes/preview.php';
$root = sys_get_temp_dir() . '/ikusasa-preview-' . bin2hex(random_bytes(8));
putenv('UPLOAD_ROOT=' . $root);
$site = storagePath('uploads/projects/browser/site', false); mkdir($site, 0700, true);
$html = '<html><head><link rel="stylesheet" href="style.css"></head><body><h1>Hello</h1><script src="app.js"></script></body></html>';
file_put_contents($site . '/index.html', $html);
file_put_contents($site . '/style.css', 'h1 { color: rgb(255, 0, 0); }');
file_put_contents($site . '/app.js', <<<'JS'
document.querySelector('h1').textContent = 'Working';
try { parent.document.querySelector('h1').textContent = 'Compromised'; document.body.dataset.parent = 'open'; }
catch (_) { document.body.dataset.parent = 'blocked'; }
try { localStorage.setItem('secret', 'bad'); document.body.dataset.storage = 'open'; }
catch (_) { document.body.dataset.storage = 'blocked'; }
fetch('https://example.com/').then(() => document.body.dataset.network = 'open').catch(() => document.body.dataset.network = 'blocked');
JS);
try { echo buildProjectPreview(['extracted_path' => 'uploads/projects/browser/site/'], 'uploads/projects/browser/site/index.html'); }
finally { removeStored('uploads/projects/browser'); rmdir($root . '/projects'); rmdir($root); }
