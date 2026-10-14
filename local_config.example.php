<?php
/**
 * Copy this file to local_config.php and update values for your local machine.
 * Do not commit local_config.php. Do not store real user records in public repos.
 */
return [
    'database' => [
        'host' => 'localhost',
        'username' => 'root',
        'password' => '',
        'database' => 'users_db',
    ],
    'mail' => [
        'host' => 'smtp.gmail.com',
        'username' => '',        // SMTP account email
        'password' => '',        // SMTP app password; keep private
        'from_email' => '',      // Usually the SMTP account email
        'recipient_email' => 'serenityspamassage25@gmail.com',
        'port' => 587,
    ],
];
?>
