<?php
declare(strict_types=1);
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
$path = substr((string) $path, strlen($base));
if (preg_match('#^/(lib|models|runtime)(/|$)#i', $path)
    || preg_match('#^/(config\.php|router\.php|README\.md|\.htaccess)$#i', $path)) {
    http_response_code(403);
    exit('Forbidden');
}
if (rtrim($path, '/') === '/classify') {
    require __DIR__ . '/classify.php';
    return true;
}
if ($path === '/' || $path === '/index.php') {
    require __DIR__ . '/index.php';
    return true;
}
http_response_code(404);
echo 'Not Found';
return true;
