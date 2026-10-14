<?php
/**
 * Load SMTP settings from ignored local_config.php.
 * A public repository intentionally contains no SMTP credentials.
 */
function serenity_mail_config(): array {
    $path = __DIR__ . '/local_config.php';
    $config = is_file($path) ? require $path : [];
    if (!is_array($config)) {
        $config = [];
    }
    $mail = $config['mail'] ?? [];
    $username = (string)($mail['username'] ?? '');
    return [
        'host' => (string)($mail['host'] ?? 'smtp.gmail.com'),
        'username' => $username,
        'password' => (string)($mail['password'] ?? ''),
        'from_email' => (string)($mail['from_email'] ?? $username),
        'recipient_email' => (string)($mail['recipient_email'] ?? 'serenityspamassage25@gmail.com'),
        'port' => (int)($mail['port'] ?? 587),
    ];
}
?>
