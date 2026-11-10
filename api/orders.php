<?php
declare(strict_types=1);
require_once __DIR__ . '/common.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $input = noah_body();
    $required = ['customerName', 'phone', 'whatsapp', 'deliveryPreference', 'location', 'paymentPreference', 'serviceType', 'goodsRequest', 'paymentReference'];
    foreach ($required as $field) {
        if (!isset($input[$field]) || trim((string)$input[$field]) === '') {
            noah_json(422, ['ok' => false, 'message' => 'Please complete all required customer and delivery details.']);
        }
    }
    if (!filter_var((string)($input['email'] ?? ''), FILTER_VALIDATE_EMAIL) && trim((string)($input['email'] ?? '')) !== '') {
        noah_json(422, ['ok' => false, 'message' => 'Please enter a valid email address.']);
    }
    $serviceFees = ['product_request' => 1000, 'product_and_seller_search' => 2000];
    $serviceType = (string)$input['serviceType'];
    if (!isset($serviceFees[$serviceType])) {
        noah_json(422, ['ok' => false, 'message' => 'Choose one of the listed sourcing services.']);
    }
    $paymentReference = trim((string)$input['paymentReference']);
    if ($paymentReference === '' || strlen($paymentReference) > 100) {
        noah_json(422, ['ok' => false, 'message' => 'Enter a valid payment reference or payment code.']);
    }
    $goodsRequest = trim(strip_tags((string)$input['goodsRequest']));
    if ($goodsRequest === '' || mb_strlen($goodsRequest) > 2000) {
        noah_json(422, ['ok' => false, 'message' => 'Describe the goods you want Noah to find (maximum 2,000 characters).']);
    }
    $items = $input['items'] ?? [];
    $catalog = require __DIR__ . '/product-catalog.php';
    if (!is_array($items) || count($items) > 40) {
        noah_json(422, ['ok' => false, 'message' => 'The selected product list is invalid.']);
    }
    $cleanItems = [];
    $total = 0;
    foreach ($items as $item) {
        if (!is_array($item) || !preg_match('/^[a-z0-9-]{2,80}$/', (string)($item['productId'] ?? ''))) {
            noah_json(422, ['ok' => false, 'message' => 'A product in your bag could not be verified.']);
        }
        $productId = (string)$item['productId'];
        if (!isset($catalog[$productId])) {
            noah_json(422, ['ok' => false, 'message' => 'A product in your bag is no longer available.']);
        }
        $quantity = filter_var($item['quantity'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 99]]);
        $price = $catalog[$productId]['price'];
        if ($quantity === false) {
            noah_json(422, ['ok' => false, 'message' => 'Product quantity or price is invalid.']);
        }
        $clean = [
            'productId' => $productId,
            'name' => $catalog[$productId]['name'],
            'quantity' => $quantity,
            'unitPrice' => $price,
            'lineTotal' => $price * $quantity,
        ];
        $total += $clean['lineTotal'];
        $cleanItems[] = $clean;
    }
    $reference = trim((string)($input['reference'] ?? ''));
    if (!preg_match('/^ND-[0-9]{4}-[A-F0-9]{16}$/', $reference)) {
        noah_json(422, ['ok' => false, 'message' => 'The order number is invalid. Please submit your request again.']);
    }
    $payload = [
        'type' => 'order',
        'reference' => $reference,
        'createdAt' => gmdate('c'),
        'customer' => [
            'name' => mb_substr(trim(strip_tags((string)$input['customerName'])), 0, 160),
            'phone' => mb_substr(trim(strip_tags((string)$input['phone'])), 0, 60),
            'whatsapp' => mb_substr(trim(strip_tags((string)$input['whatsapp'])), 0, 60),
            'email' => mb_substr(trim((string)($input['email'] ?? '')), 0, 180),
        ],
        'deliveryPreference' => mb_substr(trim(strip_tags((string)$input['deliveryPreference'])), 0, 100),
        'location' => mb_substr(trim(strip_tags((string)$input['location'])), 0, 500),
        'paymentPreference' => mb_substr(trim(strip_tags((string)$input['paymentPreference'])), 0, 100),
        'instructions' => mb_substr(trim(strip_tags((string)($input['instructions'] ?? ''))), 0, 1000),
        'items' => $cleanItems,
        'currency' => 'TZS',
        'estimatedTotal' => $total + $serviceFees[$serviceType],
        'productsEstimate' => $total,
        'serviceType' => $serviceType,
        'serviceFee' => $serviceFees[$serviceType],
        'goodsRequest' => mb_substr($goodsRequest, 0, 2000),
        'paymentReference' => mb_substr(strip_tags($paymentReference), 0, 100),
        'paymentStatus' => 'Reference submitted - pending verification',
        'status' => 'Awaiting payment verification',
    ];
    $result = noah_post_webhook(noah_env('NOAH_ORDER_WEBHOOK'), $payload);
    if (isset($result['ok']) && $result['ok'] !== true) {
        noah_json(502, ['ok' => false, 'message' => 'The external order service could not record your order. Please try again or contact Noah directly.']);
    }
    noah_json(201, ['ok' => true, 'reference' => $reference, 'message' => 'Order request saved.']);
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'GET') {
    $reference = trim((string)($_GET['reference'] ?? ''));
    $phone = trim((string)($_GET['phone'] ?? ''));
    if (!preg_match('/^ND-[0-9]{4}-[A-F0-9]{16}$/', $reference) || $phone === '') {
        noah_json(422, ['ok' => false, 'message' => 'Enter a valid order reference and phone number.']);
    }
    $result = noah_post_webhook(noah_env('NOAH_TRACK_WEBHOOK'), ['type' => 'track', 'reference' => $reference, 'phone' => mb_substr($phone, 0, 60)]);
    if (($result['found'] ?? false) !== true || !isset($result['order']) || !is_array($result['order'])) {
        noah_json(404, ['ok' => false, 'message' => 'No matching order was found. Check the reference and phone number.']);
    }
    noah_json(200, ['ok' => true, 'order' => $result['order']]);
}

header('Allow: GET, POST');
noah_json(405, ['ok' => false, 'message' => 'Method not allowed.']);
