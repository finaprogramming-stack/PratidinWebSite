<?php

declare(strict_types=1);

const PRATIDIN_DB_HOST = 'localhost';
const PRATIDIN_DB_NAME = 'u1820265_default';
const PRATIDIN_DB_USER = 'u1820265_default';
const PRATIDIN_DB_PASSWORD = '6LwshEyK4d1A2um3';
const PRATIDIN_OWNER_EMAIL = 'finaprogramming@gmail.com';
const PRATIDIN_MAIL_FROM = 'finaprogramming@gmail.com';

function pratidin_db(): mysqli
{
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

    if (PRATIDIN_DB_PASSWORD === '') {
        throw new RuntimeException('Не указан пароль БД.');
    }

    $db = new mysqli(
        PRATIDIN_DB_HOST,
        PRATIDIN_DB_USER,
        PRATIDIN_DB_PASSWORD,
        PRATIDIN_DB_NAME
    );

    $db->set_charset('utf8mb4');

    return $db;
}

function pratidin_ensure_submissions_table(mysqli $db): void
{
    $db->query(
        "CREATE TABLE IF NOT EXISTS form_submissions (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            form_type VARCHAR(32) NOT NULL,
            lang VARCHAR(8) NOT NULL,
            company VARCHAR(190) DEFAULT NULL,
            contact_person VARCHAR(190) DEFAULT NULL,
            request_text TEXT DEFAULT NULL,
            full_name VARCHAR(190) DEFAULT NULL,
            specialization VARCHAR(190) DEFAULT NULL,
            message_text TEXT DEFAULT NULL,
            email VARCHAR(190) DEFAULT NULL,
            question_text TEXT DEFAULT NULL,
            sender_ip VARCHAR(64) DEFAULT NULL,
            user_agent VARCHAR(255) DEFAULT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
    );
}
