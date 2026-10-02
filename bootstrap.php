<?php
declare(strict_types=1);

// Keep the deployed config.php and its database credentials intact.
$bufferLevel = ob_get_level();
require_once __DIR__.'/config.php';
// Older installations start a branding output buffer in config.php.
// Current pages render their own branding, so discard only that buffer.
if (defined('BRAND_OUTPUT_BUFFER') && ob_get_level() > $bufferLevel) ob_end_clean();

function startAppSession(): void
{
    if (session_status() !== PHP_SESSION_NONE) return;
    ini_set('session.use_strict_mode', '1');
    session_set_cookie_params([
        'httponly' => true,
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'samesite' => 'Lax',
        'path' => '/',
    ]);
    session_start();
}

function csrfToken(string $key): string
{
    if (empty($_SESSION[$key])) $_SESSION[$key] = bin2hex(random_bytes(32));
    return (string)$_SESSION[$key];
}

function validCsrf(string $key, ?string $submitted): bool
{
    return isset($_SESSION[$key]) && is_string($submitted)
        && hash_equals((string)$_SESSION[$key], $submitted);
}
