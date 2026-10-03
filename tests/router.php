<?php
// Development server equivalent of Docker's Apache private-directory restrictions.
$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
if (preg_match('#^/(config|includes|scripts|database|tests|docker|uploads|\.git|\.github)(/|$)#', $path)
    || preg_match('#/(\.[^/]*|Dockerfile|README\.md|[^/]*\.(sql|pem|ini|sh|yml|yaml))$#', $path)) {
    http_response_code(404); echo 'Not found'; return true;
}
return false;
