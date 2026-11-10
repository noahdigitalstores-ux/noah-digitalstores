<?php
declare(strict_types=1);

function noah_json(int $status, array $body): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($body, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

function noah_env(string $key): string
{
    $value = getenv($key);
    return $value === false ? '' : trim($value);
}

function noah_body(): array
{
    if ((int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 20000) {
        noah_json(413, ['ok' => false, 'message' => 'Request is too large.']);
    }
    $data = json_decode((string)file_get_contents('php://input'), true);
    if (!is_array($data)) {
        noah_json(400, ['ok' => false, 'message' => 'Invalid request data.']);
    }
    return $data;
}

function noah_post_webhook(string $url, array $data): array
{
    if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL) || !str_starts_with(strtolower($url), 'https://')) {
        noah_json(503, ['ok' => false, 'message' => 'The order service is not configured. Please contact Noah directly.']);
    }
    if (!function_exists('curl_init')) {
        error_log('Noah store: PHP cURL extension is unavailable.');
        noah_json(503, ['ok' => false, 'message' => 'The order service is unavailable. Please contact Noah directly.']);
    }
    $curl = curl_init($url);
    curl_setopt_array($curl, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($data, JSON_UNESCAPED_SLASHES),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_TIMEOUT => 25,
        CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
    ]);
    $response = curl_exec($curl);
    $status = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $error = curl_error($curl);
    curl_close($curl);
    if ($response === false || $status < 200 || $status >= 300) {
        error_log('Noah store webhook error. HTTP ' . $status . '; ' . $error);
        noah_json(502, ['ok' => false, 'message' => 'The external order service did not accept the request. Your bag is still saved; please try again or contact Noah directly.']);
    }
    $decoded = json_decode((string)$response, true);
    return is_array($decoded) ? $decoded : [];
}
