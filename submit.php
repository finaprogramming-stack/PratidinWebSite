<?php

declare(strict_types=1);

require __DIR__ . '/db.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$lang = $_POST['lang'] ?? 'ru';
$messages = [
    'ru' => [
        'success' => 'Заявка успешно отправлена.',
        'success_mail_warning' => 'Заявка сохранена, но уведомление по email не было отправлено. Проверьте настройки почты на сервере.',
        'error' => 'Не удалось отправить форму. Попробуйте еще раз.',
        'invalid' => 'Пожалуйста, заполните обязательные поля.'
    ],
    'en' => [
        'success' => 'Your request was sent successfully.',
        'success_mail_warning' => 'Your request was saved, but the email notification was not sent. Check the server mail configuration.',
        'error' => 'The form could not be submitted. Please try again.',
        'invalid' => 'Please fill in the required fields.'
    ],
    'bn' => [
        'success' => 'আপনার অনুরোধ সফলভাবে পাঠানো হয়েছে।',
        'success_mail_warning' => 'আপনার অনুরোধ সংরক্ষণ হয়েছে, কিন্তু ইমেইল নোটিফিকেশন পাঠানো যায়নি। সার্ভারের মেইল সেটিংস পরীক্ষা করুন।',
        'error' => 'ফর্ম পাঠানো যায়নি। আবার চেষ্টা করুন।',
        'invalid' => 'অনুগ্রহ করে প্রয়োজনীয় ঘরগুলো পূরণ করুন।'
    ]
];
$copy = $messages[$lang] ?? $messages['ru'];

$formType = trim((string) ($_POST['form_type'] ?? 'general'));
$allowedTypes = ['employers', 'candidates', 'general'];
if (!in_array($formType, $allowedTypes, true)) {
    $formType = 'general';
}

$company = trim((string) ($_POST['company'] ?? ''));
$contact = trim((string) ($_POST['contact'] ?? ''));
$request = trim((string) ($_POST['request'] ?? ''));
$name = trim((string) ($_POST['name'] ?? ''));
$specialization = trim((string) ($_POST['specialization'] ?? ''));
$message = trim((string) ($_POST['message'] ?? ''));
$email = trim((string) ($_POST['email'] ?? ''));
$question = trim((string) ($_POST['question'] ?? ''));
$senderIp = substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 64);
$userAgent = substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);

$valid = match ($formType) {
    'employers' => $company !== '' && $contact !== '' && $request !== '',
    'candidates' => $name !== '' && $specialization !== '' && $message !== '',
    default => $name !== '' && $email !== '' && $question !== ''
};

if (!$valid) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => $copy['invalid']], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => $copy['invalid']], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $db = pratidin_db();
    pratidin_ensure_submissions_table($db);

    $statement = $db->prepare(
        'INSERT INTO form_submissions (
            form_type,
            lang,
            company,
            contact_person,
            request_text,
            full_name,
            specialization,
            message_text,
            email,
            question_text,
            sender_ip,
            user_agent
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );

    $statement->bind_param(
        'ssssssssssss',
        $formType,
        $lang,
        $company,
        $contact,
        $request,
        $name,
        $specialization,
        $message,
        $email,
        $question,
        $senderIp,
        $userAgent
    );
    $statement->execute();

    $submissionId = $db->insert_id;
    $mailSent = pratidin_send_submission_email(
        $formType,
        [
            'id' => $submissionId,
            'lang' => $lang,
            'company' => $company,
            'contact' => $contact,
            'request' => $request,
            'name' => $name,
            'specialization' => $specialization,
            'message' => $message,
            'email' => $email,
            'question' => $question,
            'sender_ip' => $senderIp,
            'user_agent' => $userAgent
        ]
    );

    echo json_encode(
        [
            'success' => true,
            'mail_sent' => $mailSent,
            'message' => $mailSent ? $copy['success'] : $copy['success_mail_warning']
        ],
        JSON_UNESCAPED_UNICODE
    );
} catch (Throwable $exception) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $copy['error']], JSON_UNESCAPED_UNICODE);
}

function pratidin_send_submission_email(string $formType, array $data): bool
{
    $titles = [
        'employers' => 'Employers',
        'candidates' => 'Candidates',
        'general' => 'General Queries'
    ];

    $subject = sprintf(
        '=?UTF-8?B?%s?=',
        base64_encode('Pratidin Online Limited: новая заявка [' . ($titles[$formType] ?? 'General') . ']')
    );

    $lines = [
        'Новая заявка с сайта Pratidin Online Limited',
        'ID: ' . ($data['id'] ?? ''),
        'Тип формы: ' . $formType,
        'Язык: ' . ($data['lang'] ?? ''),
        'Компания: ' . ($data['company'] ?? '-'),
        'Контактное лицо: ' . ($data['contact'] ?? '-'),
        'Имя: ' . ($data['name'] ?? '-'),
        'Специализация: ' . ($data['specialization'] ?? '-'),
        'Email: ' . ($data['email'] ?? '-'),
        'Запрос: ' . ($data['request'] ?? '-'),
        'Сообщение: ' . ($data['message'] ?? '-'),
        'Вопрос: ' . ($data['question'] ?? '-'),
        'IP: ' . ($data['sender_ip'] ?? '-'),
        'User-Agent: ' . ($data['user_agent'] ?? '-')
    ];

    $headers = [
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'From: Pratidin Online Limited <' . PRATIDIN_MAIL_FROM . '>',
        'Reply-To: ' . (($data['email'] ?? '') !== '' ? $data['email'] : PRATIDIN_OWNER_EMAIL),
        'X-Mailer: PHP/' . PHP_VERSION
    ];

    $sent = @mail(
        PRATIDIN_OWNER_EMAIL,
        $subject,
        implode(PHP_EOL, $lines),
        implode("\r\n", $headers)
    );

    if (!$sent) {
        error_log('Pratidin mail() failed for submission #' . ($data['id'] ?? 'unknown'));
    }

    return $sent;
}
