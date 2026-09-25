<?php
declare(strict_types=1);
class UserError extends RuntimeException {
    public function __construct(string $message, public readonly int $status = 422) { parent::__construct($message); }
}
function e(string $text): string { return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function app_url(string $path = 'index.php', array $query = []): string {
    global $config;
    return rtrim($config['base_path'] ?? '', '/').'/'.ltrim($path, '/').($query ? '?'.http_build_query($query, '', '&', PHP_QUERY_RFC3986) : '');
}
function route_url(string $route, array $args = []): string { return app_url('index.php', ['route'=>$route] + $args); }
function input_string(array $source, string $key, string $default = ''): string {
    if (!isset($source[$key])) return $default;
    if (!is_string($source[$key])) throw new UserError('Invalid input.', 400);
    return $source[$key];
}
function input_id(array $source, string $key, bool $optional = false): ?int {
    $v = input_string($source, $key);
    if ($v === '' && $optional) return null;
    if (!preg_match('/^[1-9][0-9]{0,9}$/D', $v) || (int)$v > 2147483647) throw new UserError('Invalid identifier.', 400);
    return (int)$v;
}
function csrf_token(): string { return $_SESSION['csrf'] ??= bin2hex(random_bytes(32)); }
function verify_csrf(mixed $token): void {
    if (!is_string($token) || !isset($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $token)) throw new UserError('Your form has expired. Reload the page and try again.', 403);
}
function csrf_field(): string { return '<input type="hidden" name="csrf" value="'.e(csrf_token()).'">'; }
function flash(string $message): void { $_SESSION['flash'] = $message; }
function redirect_to(string $route, array $args = []): never { header('Location: '.route_url($route, $args), true, 303); exit; }
function formatted_date(string $utc): string {
    global $config;
    return (new DateTimeImmutable($utc, new DateTimeZone('UTC')))->setTimezone(new DateTimeZone($config['timezone'] ?? 'UTC'))->format('M j, Y');
}
function excerpt(string $text, int $length = 190): string {
    $text = trim(preg_replace('/\s+/u', ' ', $text) ?? '');
    return mb_strlen($text) > $length ? mb_substr($text, 0, $length).'…' : $text;
}
