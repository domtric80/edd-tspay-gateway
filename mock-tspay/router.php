<?php

declare(strict_types=1);

$path       = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/';
$method     = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$publicUrl  = rtrim(getenv('MOCK_PUBLIC_URL') ?: 'http://localhost:8090', '/');
$apiKey     = getenv('MOCK_API_KEY') ?: 'mock-api-key';
$merchant   = getenv('MOCK_MERCHANT_REF') ?: 'MOCKMERCHANT';
$storeFile  = '/data/orders.json';

function respondJson(array $data, int $status = 200): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function loadOrders(string $file): array {
    if (!is_file($file)) {
        return [];
    }
    $decoded = json_decode((string) file_get_contents($file), true);
    return is_array($decoded) ? $decoded : [];
}

function saveOrders(string $file, array $orders): void {
    file_put_contents($file, json_encode($orders, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
}

function requireAuth(string $apiKey): void {
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (!hash_equals('Bearer ' . $apiKey, $header)) {
        respondJson(['statusCode' => 401, 'message' => 'Unauthorized'], 401);
    }
}

if ('GET' === $method && '/' === $path) {
    respondJson(['name' => 'TS Pay mock', 'status' => 'ok']);
}

if ('GET' === $method && '/auth/' === $path) {
    requireAuth($apiKey);
    respondJson(['x-auth-type' => 'API_KEY', 'x-app-ref' => 'EDD-MOCK', 'x-merchant-ref' => $merchant]);
}

if ('POST' === $method && '/orders/link2pay' === $path) {
    requireAuth($apiKey);
    $payload = json_decode((string) file_get_contents('php://input'), true);
    if (!is_array($payload) || empty($payload['amount']) || 'EUR' !== ($payload['currency'] ?? '')) {
        respondJson(['statusCode' => 400, 'message' => 'Invalid LinkToPay payload'], 400);
    }
    $orderKey = 'mock_' . bin2hex(random_bytes(12));
    $orders = loadOrders($storeFile);
    $orders[$orderKey] = [
        'orderKey' => $orderKey,
        'state' => 'unpaid',
        'payload' => $payload,
        'createdOn' => gmdate('c'),
    ];
    saveOrders($storeFile, $orders);
    respondJson(['orderKey' => $orderKey, 'url' => $publicUrl . '/pay?orderKey=' . rawurlencode($orderKey)]);
}

if ('GET' === $method && preg_match('#^/charges/orders/([^/]+)$#', $path, $matches)) {
    requireAuth($apiKey);
    $orderKey = rawurldecode($matches[1]);
    $orders = loadOrders($storeFile);
    if (!isset($orders[$orderKey])) {
        respondJson(['statusCode' => 404, 'message' => 'Order not found'], 404);
    }
    $record = $orders[$orderKey];
    if ('unpaid' === $record['state']) {
        respondJson([]);
    }
    $payload = $record['payload'];
    respondJson([[
        'amount' => (string) $payload['amount'],
        'currency' => $payload['currency'],
        'chargeKey' => 'charge_' . substr(hash('sha256', $orderKey), 0, 24),
        'orderKey' => $orderKey,
        'state' => $record['state'],
        'payMethod' => ['type' => 'card', 'card' => ['brand' => 'visa', 'last4' => '4242']],
        'order' => $payload,
        'createdOn' => $record['createdOn'],
        'modifiedOn' => gmdate('c'),
    ]]);
}

if ('GET' === $method && '/pay' === $path) {
    $orderKey = isset($_GET['orderKey']) ? (string) $_GET['orderKey'] : '';
    $orders = loadOrders($storeFile);
    if (!isset($orders[$orderKey])) {
        http_response_code(404);
        echo 'Ordine mock non trovato';
        exit;
    }
    $record = $orders[$orderKey];
    $amount = number_format(((int) $record['payload']['amount']) / 100, 2, ',', '.');
    header('Content-Type: text/html; charset=utf-8');
    ?>
<!doctype html><html lang="it"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>TS Pay Mock</title><style>body{font-family:system-ui;background:#f4f7fb;margin:0;padding:3rem}.card{max-width:560px;margin:auto;background:#fff;border-radius:16px;padding:2rem;box-shadow:0 10px 35px #18315318}h1{color:#123b63}.amount{font-size:2.2rem;font-weight:700}.actions{display:grid;gap:.75rem;margin-top:2rem}a{padding:.85rem 1rem;border-radius:8px;text-align:center;text-decoration:none;background:#0875be;color:#fff}a.fail{background:#b42318}a.pending{background:#946200}a.cancel{background:#667085}</style></head>
<body><main class="card"><h1>TS Pay — simulatore</h1><p><?= htmlspecialchars($record['payload']['template']['paymentRef'] ?? '', ENT_QUOTES) ?></p><p class="amount">€ <?= $amount ?></p><p>Usa uno degli esiti per collaudare il gateway EDD.</p><div class="actions">
<a href="/complete?orderKey=<?= rawurlencode($orderKey) ?>&state=active">Pagamento riuscito</a>
<a class="pending" href="/complete?orderKey=<?= rawurlencode($orderKey) ?>&state=pending">Pagamento in elaborazione</a>
<a class="fail" href="/complete?orderKey=<?= rawurlencode($orderKey) ?>&state=failed">Pagamento fallito</a>
<a class="cancel" href="/complete?orderKey=<?= rawurlencode($orderKey) ?>&state=cancel">Annulla</a>
</div></main></body></html>
    <?php
    exit;
}

if ('GET' === $method && '/complete' === $path) {
    $orderKey = isset($_GET['orderKey']) ? (string) $_GET['orderKey'] : '';
    $state = isset($_GET['state']) ? (string) $_GET['state'] : 'active';
    $orders = loadOrders($storeFile);
    if (!isset($orders[$orderKey])) {
        http_response_code(404);
        exit('Ordine mock non trovato');
    }
    $record = $orders[$orderKey];
    if ('cancel' === $state) {
        header('Location: ' . $record['payload']['cancelUrl'], true, 302);
        exit;
    }
    $record['state'] = in_array($state, ['active', 'pending', 'failed'], true) ? $state : 'failed';
    $orders[$orderKey] = $record;
    saveOrders($storeFile, $orders);
    header('Location: ' . $record['payload']['callbackUrl'], true, 302);
    exit;
}

respondJson(['statusCode' => 404, 'message' => 'Not found'], 404);

