<?php
/**
 * Re-run image search with a custom query.
 * POST params:
 *   query  string  The search query to send to Serper.dev Images
 */
require_once dirname(__FILE__) . '/../_config.php';

header('Cache-Control: no-cache');
header('Content-type: application/json');

$query = trim((string) ($_POST['query'] ?? ''));

if ($query === '') {
    echo json_encode(['success' => false, 'error' => 'Missing query.']);
    exit;
}

$apiKey  = 'b3c39559a928534f00749286e3b8503856c72c02';
$payload = json_encode(['q' => $query, 'num' => 10]);

$ch = curl_init('https://google.serper.dev/images');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_HTTPHEADER     => [
        'X-API-KEY: ' . $apiKey,
        'Content-Type: application/json',
    ],
    CURLOPT_POSTFIELDS     => $payload,
    CURLOPT_TIMEOUT        => 30,
    CURLOPT_CONNECTTIMEOUT => 10,
]);
$response = curl_exec($ch);
$curlErr  = curl_error($ch);
$httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($response === false || $curlErr || $httpCode >= 300) {
    echo json_encode(['success' => false, 'error' => 'Serper request failed (HTTP ' . $httpCode . ')']);
    exit;
}

$json   = json_decode($response, true);
$images = $json['images'] ?? [];
$urls   = [];
foreach ($images as $img) {
    $url = trim((string) ($img['imageUrl'] ?? ''));
    if ($url !== '') $urls[] = $url;
    if (count($urls) >= 10) break;
}

echo json_encode([
    'success'      => true,
    'images'       => $urls,
    'search_query' => $query,
    'image_source' => 'Web Search',
]);
