<?php
declare(strict_types=1);
require_once __DIR__ . '/common.php';
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    noah_json(405, ['ok' => false, 'message' => 'Method not allowed.']);
}
$input = noah_body();
foreach (['name', 'phone', 'message'] as $field) {
    if (!isset($input[$field]) || trim((string)$input[$field]) === '') {
        noah_json(422, ['ok' => false, 'message' => 'Please complete your name, phone and enquiry.']);
    }
}
$email = trim((string)($input['email'] ?? ''));
if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    noah_json(422, ['ok' => false, 'message' => 'Please enter a valid email address.']);
}
$payload = [
    'type' => 'enquiry',
    'reference' => 'NE-' . gmdate('Y') . '-' . strtoupper(bin2hex(random_bytes(4))),
    'createdAt' => gmdate('c'),
    'name' => mb_substr(trim(strip_tags((string)$input['name'])), 0, 160),
    'phone' => mb_substr(trim(strip_tags((string)$input['phone'])), 0, 60),
    'email' => mb_substr($email, 0, 180),
    'message' => mb_substr(trim(strip_tags((string)$input['message'])), 0, 2000),
];
$result = noah_post_webhook(noah_env('NOAH_ENQUIRY_WEBHOOK'), $payload);
if (isset($result['ok']) && $result['ok'] !== true) {
    noah_json(502, ['ok' => false, 'message' => 'The enquiry service could not record your message. Please contact Noah directly.']);
}
noah_json(201, ['ok' => true, 'reference' => $payload['reference']]);
