<?php
declare(strict_types=1);

/**
 * TacacsGUI front controller (Angie/Nginx).
 *
 * Routes:
 *   /api/*  -> strip "/api", dispatch to the Slim 4 app (web/api/index.php)
 *   /       -> serve the Angular SPA (web/index.html) for client-side routing
 *   /other  -> serve the static file if it exists, else fall back to index.html
 *
 * The Angular bundle calls the API with an "/api/" prefix (api/auth/, api/tacacs/, ...);
 * the Slim routes are defined WITHOUT that prefix (e.g. /auth/signin/), so we strip it.
 */

$uri = $_SERVER['REQUEST_URI'] ?? '/';
$path = parse_url($uri, PHP_URL_PATH) ?: '/';

// --- API dispatch: /api/foo/bar -> /foo/bar -> Slim app ---
if (strpos($path, '/api/') === 0) {
    // Rewrite the path Slim sees: drop the leading /api
    $_SERVER['REQUEST_URI'] = substr($uri, 4); // strip "/api"
    $_SERVER['SCRIPT_NAME'] = '/api/index.php';
    $_SERVER['SCRIPT_FILENAME'] = __DIR__ . '/api/index.php';
    $_SERVER['PHP_SELF'] = '/api/index.php';
    require __DIR__ . '/api/index.php';
    return;
}

// --- Static files: serve directly if the file exists under web/ ---
$staticFile = __DIR__ . $path;
if ($path !== '/' && is_file($staticFile)) {
    return; // let the web server serve the static file (Angie try_files handles this)
}

// --- SPA fallback: serve Angular index.html ---
$indexHtml = __DIR__ . '/index.html';
if (is_file($indexHtml)) {
    header('Content-Type: text/html; charset=utf-8');
    readfile($indexHtml);
    return;
}

http_response_code(404);
echo "Not Found";
