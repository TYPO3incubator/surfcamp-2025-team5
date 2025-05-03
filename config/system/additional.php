<?php

$dotenv = Dotenv\Dotenv::createUnsafeMutable(__DIR__ . '/../../', '.env.dist');
$dotenv->load();

if (file_exists(__DIR__ . '/../../.env')) {
    $dotenv = Dotenv\Dotenv::createUnsafeMutable(__DIR__ . '/../../');
    $dotenv->load();
}

$GLOBALS['TYPO3_CONF_VARS'] = array_replace_recursive($GLOBALS['TYPO3_CONF_VARS'], [
    'BE' => [
        'debug' => $_ENV['TYPO3_BE_DEBUG'] ?? 0,
        'installToolPassword' => $_ENV['TYPO3_BE_INSTALLTOOLPASSWORD'] ?? '',
    ],
    'FE' => [
        'debug' => $_ENV['TYPO3_FE_DEBUG'] ?? 0,
    ],
    'DB' => [
        'Connections' => [
            'Default' => [
                'host' => $_ENV['TYPO3_DB_CONNECTIONS_DEFAULT_HOST'],
                'port' => (int)($_ENV['TYPO3_DB_CONNECTIONS_DEFAULT_PORT'] ?? 3306),
                'user' => $_ENV['TYPO3_DB_CONNECTIONS_DEFAULT_USER'],
                'password' => $_ENV['TYPO3_DB_CONNECTIONS_DEFAULT_PASSWORD'],
                'dbname' => $_ENV['TYPO3_DB_CONNECTIONS_DEFAULT_DBNAME'],
            ],
        ],
    ],
    'MAIL' => [
        'defaultMailFromAddress' => $_ENV['TYPO3_MAIL_DEFAULTMAILFROMADDRESS'] ?? '',
        'defaultMailFromName' => $_ENV['TYPO3_MAIL_DEFAULTMAILFROMNAME'] ?? '',
        'transport' => $_ENV['TYPO3_MAIL_TRANSPORT'],
        'transport_mbox_file' => $_ENV['TYPO3_MAIL_TRANSPORT_MBOX_FILE'],
        'transport_sendmail_command' => $_ENV['TYPO3_MAIL_TRANSPORT_SENDMAIL_COMMAND'],
        'transport_smtp_encrypt' => $_ENV['TYPO3_MAIL_TRANSPORT_SMTP_ENCRYPT'],
        'transport_smtp_password' => $_ENV['TYPO3_MAIL_TRANSPORT_SMTP_PASSWORD'],
        'transport_smtp_server' => $_ENV['TYPO3_MAIL_TRANSPORT_SMTP_SERVER'],
        'transport_smtp_username' => $_ENV['TYPO3_MAIL_TRANSPORT_SMTP_USERNAME'],
    ],
    'SYS' => [
        'devIPmask' => $_ENV['TYPO3_SYS_DEVIPMASK'] ?? '',
        'displayErrors' => $_ENV['TYPO3_SYS_DISPLAYERRORS'] ?? 0,
        'encryptionKey' => $_ENV['TYPO3_SYS_ENCRYPTIONKEY'],
        'exceptionalErrors' => $_ENV['TYPO3_SYS_EXCEPTIONALERRORS'] ?? 4096,
    ],
]);
