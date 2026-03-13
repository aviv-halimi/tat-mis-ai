<?php
require_once dirname(__FILE__) . '/../_config.php';

header('Cache-Control: no-cache');
header('Content-type: application/json');

$po_product_id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
$product_name  = isset($_POST['name']) ? trim((string) $_POST['name']) : '';
$brand_name    = isset($_POST['brand']) ? trim((string) $_POST['brand']) : '';
$category_name = isset($_POST['category']) ? trim((string) $_POST['category']) : '';

if ($po_product_id <= 0 || $product_name === '') {
    echo json_encode([
        'success' => false,
        'error'   => 'Missing product id or name for enrichment.',
    ]);
    exit;
}

/**
 * Step A: Description Generation via Gemini (text only).
 */
function tat_enrich_generate_description($product_name, $brand_name, $category_name, &$error = null)
{
    $error = null;

    $apiKey = getenv('GEMINI_API_KEY');
    if ($apiKey === false || $apiKey === '') {
        if (defined('GEMINI_API_KEY') && GEMINI_API_KEY !== '') {
            $apiKey = GEMINI_API_KEY;
        }
    }
    if (!$apiKey) {
        $error = 'Gemini API key is not configured.';
        return '';
    }

    $model = (defined('GEMINI_MODEL') && GEMINI_MODEL !== '') ? GEMINI_MODEL : 'gemini-2.0-flash';
    $url   = 'https://generativelanguage.googleapis.com/v1beta/models/' . urlencode($model) . ':generateContent?key=' . urlencode($apiKey);

    $prompt = "Act as a cannabis retail copywriter. Write a compelling, 3-sentence product description for {$product_name}";
    if ($brand_name !== '') {
        $prompt .= " by {$brand_name}";
    }
    if ($category_name !== '') {
        $prompt .= " in the {$category_name} category";
    }
    $prompt .= ". Focus on effects and quality. Output: Raw text only.";

    $payload = [
        'contents' => [
            [
                'parts' => [
                    ['text' => $prompt],
                ],
            ],
        ],
        'generationConfig' => [
            'temperature'     => 0.7,
            'maxOutputTokens' => 256,
        ],
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_TIMEOUT        => 60,
        CURLOPT_CONNECTTIMEOUT => 10,
    ]);

    $response = curl_exec($ch);
    $curlErr  = curl_error($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false || $curlErr || $httpCode >= 300) {
        $error = 'Gemini description request failed' . ($curlErr ? ': ' . $curlErr : '') . ($httpCode ? " (HTTP {$httpCode})" : '');
        return '';
    }

    $json = json_decode($response, true);
    $text = $json['candidates'][0]['content']['parts'][0]['text'] ?? '';
    $text = trim((string) $text);

    if ($text === '') {
        $error = 'Gemini returned an empty description.';
    }

    return $text;
}

/**
 * Clean a product name for image search:
 * removes parenthetical strain codes like (I), (S), (H) and collapses whitespace.
 */
function tat_enrich_clean_name(string $name): string
{
    $clean = preg_replace('/\s*\([^)]*\)\s*/', ' ', $name);
    return trim(preg_replace('/\s+/', ' ', $clean));
}

/**
 * Send one image query to Serper.dev and return the first imageUrl, or null.
 */
function tat_serper_image_search(string $query, string $apiKey): ?string
{
    $payload = json_encode(['q' => $query, 'num' => 1]);

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
        return null;
    }

    $json = json_decode($response, true);
    $images = $json['images'] ?? [];
    if (!is_array($images) || empty($images)) {
        return null;
    }

    $imageUrl = trim((string) ($images[0]['imageUrl'] ?? ''));
    return $imageUrl !== '' ? $imageUrl : null;
}

/**
 * Step B: Image discovery — Google Drive (placeholder) then Serper.dev Images API.
 * Primary query:  "[cleanName] cannabis product"
 * Fallback query: "[brand] [cleanName] cannabis"
 */
function tat_enrich_discover_image($product_name, $brand_name, &$source_found, &$warning = null)
{
    $source_found = null;
    $warning      = null;

    // Step B1: Google Drive (conditional — stub until Drive integration is wired up).
    $service_account_path = BASE_PATH . 'credentials/service-account.json';
    if (file_exists($service_account_path)) {
        // TODO: search brand folder for product image when Drive integration is available.
    }

    // Step B2: Serper.dev image search.
    $apiKey = 'b3c39559a928534f00749286e3b8503856c72c02';

    // Clean strain-abbreviation parentheticals from the product name.
    $cleanName = tat_enrich_clean_name((string) $product_name);
    if ($cleanName === '') {
        $warning = 'Product name is empty after cleaning; image search skipped.';
        return null;
    }

    // Primary query: product name + "cannabis product".
    $primaryQuery = $cleanName . ' cannabis product';
    $imageUrl = tat_serper_image_search($primaryQuery, $apiKey);

    // Fallback query: brand + product name + "cannabis".
    if (!$imageUrl) {
        $fallbackParts = array_filter([$brand_name, $cleanName, 'cannabis']);
        $fallbackQuery = implode(' ', $fallbackParts);
        $imageUrl = tat_serper_image_search($fallbackQuery, $apiKey);
    }

    if (!$imageUrl) {
        $warning = 'No Image Found';
        return null;
    }

    $source_found = 'Web (Serper.dev)';
    return $imageUrl;
}

/**
 * Download image bytes from a URL using cURL (handles redirects and browser-like headers).
 */
function tat_enrich_fetch_image_bytes(string $url): string
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 5,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (compatible; EnrichBot/1.0)',
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $bytes = curl_exec($ch);
    curl_close($ch);
    return (is_string($bytes) && strlen($bytes) > 0) ? $bytes : '';
}

/**
 * Step C: AI validation + image processing.
 * Returns a base64 data URI (no filesystem writes needed) or null on failure.
 */
function tat_enrich_validate_and_process_image($image_url, $product_name, &$is_valid, &$error = null)
{
    $is_valid = false;
    $error    = null;

    if (!$image_url) {
        $error = 'Missing image URL.';
        return null;
    }

    // Download image bytes once; reuse for both validation and processing.
    $imgBytes = tat_enrich_fetch_image_bytes($image_url);
    if ($imgBytes === '') {
        $error = 'Could not download image.';
        return null;
    }

    // Gemini validation (multimodal).
    $apiKey = getenv('GEMINI_API_KEY');
    if ($apiKey === false || $apiKey === '') {
        if (defined('GEMINI_API_KEY') && GEMINI_API_KEY !== '') {
            $apiKey = GEMINI_API_KEY;
        }
    }

    if (!$apiKey) {
        // No Gemini key — skip validation, proceed to process.
        $is_valid = true;
    } else {
        $model   = (defined('GEMINI_MODEL') && GEMINI_MODEL !== '') ? GEMINI_MODEL : 'gemini-2.0-flash';
        $url     = 'https://generativelanguage.googleapis.com/v1beta/models/' . urlencode($model) . ':generateContent?key=' . urlencode($apiKey);
        $prompt  = "Is this image a specific product shot for {$product_name}? If it is just a logo, a 'no image' placeholder, or a different product, return 'INVALID'. Otherwise return 'VALID'.";

        $payload = [
            'contents' => [[
                'parts' => [
                    ['inline_data' => ['mime_type' => 'image/jpeg', 'data' => base64_encode($imgBytes)]],
                    ['text' => $prompt],
                ],
            ]],
            'generationConfig' => ['temperature' => 0, 'maxOutputTokens' => 8],
        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_TIMEOUT        => 60,
            CURLOPT_CONNECTTIMEOUT => 10,
        ]);
        $response = curl_exec($ch);
        $curlErr  = curl_error($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false || $curlErr || $httpCode >= 300) {
            // Validation call failed — optimistically proceed rather than block.
            $is_valid = true;
        } else {
            $json   = json_decode($response, true);
            $text   = strtoupper(trim((string) ($json['candidates'][0]['content']['parts'][0]['text'] ?? '')));
            if ($text === 'VALID') {
                $is_valid = true;
            } else {
                $is_valid = false;
                $error    = 'Gemini marked the image as invalid.';
                return null;
            }
        }
    }

    // Process image: resize to 800×800 with white letterbox, return as base64 data URI.
    $src = @imagecreatefromstring($imgBytes);
    if (!$src) {
        $error = 'Could not decode image for processing.';
        return null;
    }

    $srcW    = imagesx($src);
    $srcH    = imagesy($src);
    $targetW = 800;
    $targetH = 800;
    $scale   = min($targetW / $srcW, $targetH / $srcH);
    $newW    = max(1, (int) floor($srcW * $scale));
    $newH    = max(1, (int) floor($srcH * $scale));
    $dstX    = (int) floor(($targetW - $newW) / 2);
    $dstY    = (int) floor(($targetH - $newH) / 2);

    $dst   = imagecreatetruecolor($targetW, $targetH);
    $white = imagecolorallocate($dst, 255, 255, 255);
    imagefilledrectangle($dst, 0, 0, $targetW, $targetH, $white);
    imagecopyresampled($dst, $src, $dstX, $dstY, 0, 0, $newW, $newH, $srcW, $srcH);
    imagedestroy($src);

    ob_start();
    imagejpeg($dst, null, 88);
    imagedestroy($dst);
    $jpegBytes = ob_get_clean();

    if (!$jpegBytes) {
        $error = 'Failed to encode processed image.';
        return null;
    }

    return 'data:image/jpeg;base64,' . base64_encode($jpegBytes);
}

// ---- Run the enrichment waterfall ----

$descError = null;
$description = tat_enrich_generate_description($product_name, $brand_name, $category_name, $descError);

$source_found = null;
$imageWarning = null;
$image_url    = tat_enrich_discover_image($product_name, $brand_name, $source_found, $imageWarning);

$temp_image_url = null;
$validationError = null;

if ($image_url) {
    $is_valid = false;
    $temp_image_url = tat_enrich_validate_and_process_image($image_url, $product_name, $is_valid, $validationError);
    if (!$is_valid || !$temp_image_url) {
        $temp_image_url = null;
        if (!$imageWarning) {
            $imageWarning = $validationError ?: 'Image failed validation.';
        }
    }
}

// Fallback placeholder when no valid image.
if (!$temp_image_url) {
    // Tiny transparent PNG as inline placeholder.
    $temp_image_url = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMB/ax3CXEAAAAASUVORK5CYII=';
    if (!$imageWarning) {
        $imageWarning = 'No Image Found';
    }
}

if ($descError && $description === '') {
    echo json_encode([
        'success' => false,
        'error'   => $descError,
    ]);
    exit;
}

echo json_encode([
    'success'        => true,
    'description'    => $description,
    'temp_image_url' => $temp_image_url,
    'source_found'   => $source_found ?: 'Web',
    'warning'        => $imageWarning,
    'brand'          => $brand_name,
    'category'       => $category_name,
]);
