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
 * Step B: Image discovery — Google Drive (placeholder, no-op) then Google Custom Search.
 */
function tat_enrich_discover_image($product_name, $brand_name, &$source_found, &$warning = null)
{
    $source_found = null;
    $warning      = null;

    // Step B1: Google Drive (conditional). Placeholder implementation: checks for credentials but does not query Drive.
    $service_account_path = BASE_PATH . 'credentials/service-account.json';
    if (file_exists($service_account_path)) {
        // TODO: Implement Google Drive search by brand folder and product name when Drive integration details are available.
        // For now, proceed to web search even if credentials exist.
    }

    // Step B2: Google Custom Search (image search).
    $apiKey = getenv('GOOGLE_SEARCH_API_KEY');
    if ($apiKey === false || $apiKey === '') {
        $apiKey = defined('GOOGLE_SEARCH_API_KEY') && GOOGLE_SEARCH_API_KEY !== '' ? GOOGLE_SEARCH_API_KEY : '';
    }
    if ($apiKey === '') {
        $warning = 'Google Search API key (GOOGLE_SEARCH_API_KEY) is not configured; image search skipped.';
        return null;
    }

    $cx = getenv('GOOGLE_SEARCH_CX');
    if ($cx === false || $cx === '') {
        if (defined('GOOGLE_SEARCH_CX') && GOOGLE_SEARCH_CX !== '') {
            $cx = GOOGLE_SEARCH_CX;
        } else {
            $cx = 'f6f49c82b49524b1d';
        }
    }

    // Use only the product name for search: strip parenthetical content and collapse spaces.
    $search_name = preg_replace('/\s*\([^)]*\)\s*/', ' ', (string) $product_name);
    $search_name = trim(preg_replace('/\s+/', ' ', $search_name));
    if ($search_name === '') {
        $warning = 'Product name is empty after cleaning; image search skipped.';
        return null;
    }
    $query = 'site:weedmaps.com "' . $search_name . '"';

    $params = [
        'key'        => $apiKey,
        'cx'         => $cx,
        'searchType' => 'image',
        'q'          => $query,
        'num'        => 5,
    ];

    $url = 'https://www.googleapis.com/customsearch/v1?' . http_build_query($params);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
    ]);
    $response = curl_exec($ch);
    $curlErr  = curl_error($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false || $curlErr || $httpCode >= 300) {
        $warning = 'Google Custom Search failed; no image found.';
        return null;
    }

    $json = json_decode($response, true);
    if (!isset($json['items']) || !is_array($json['items']) || empty($json['items'])) {
        $warning = 'No image results returned from Google Search.';
        return null;
    }

    foreach ($json['items'] as $item) {
        $link = isset($item['link']) ? trim((string) $item['link']) : '';
        if ($link === '') {
            continue;
        }
        // Simple filter: prefer standard image extensions.
        if (!preg_match('~\.(jpe?g|png|webp|gif)(\?|#|$)~i', $link)) {
            continue;
        }

        $source_found = 'Web (Google Search)';
        return $link;
    }

    $warning = 'No suitable image link found in Google Search results.';
    return null;
}

/**
 * Step C: AI validation + formatting (Gemini multimodal + Intervention/Image or GD).
 */
function tat_enrich_validate_and_process_image($image_url, $product_name, &$is_valid, &$error = null)
{
    $is_valid = false;
    $error    = null;

    if (!$image_url) {
        $error = 'Missing image URL.';
        return null;
    }

    $apiKey = getenv('GEMINI_API_KEY');
    if ($apiKey === false || $apiKey === '') {
        if (defined('GEMINI_API_KEY') && GEMINI_API_KEY !== '') {
            $apiKey = GEMINI_API_KEY;
        }
    }
    if (!$apiKey) {
        // If we cannot validate via AI, optimistically skip validation and just process the image.
        $is_valid = true;
    } else {
        $model = (defined('GEMINI_MODEL') && GEMINI_MODEL !== '') ? GEMINI_MODEL : 'gemini-2.0-flash';
        $url   = 'https://generativelanguage.googleapis.com/v1beta/models/' . urlencode($model) . ':generateContent?key=' . urlencode($apiKey);

        // Download image bytes for inlineData.
        $imgBytes = @file_get_contents($image_url);
        if ($imgBytes === false || strlen($imgBytes) === 0) {
            $error = 'Could not download image for validation.';
            return null;
        }

        $prompt = "Is this image a specific product shot for {$product_name}? If it is just a logo, a 'no image' placeholder, or a different product, return 'INVALID'. Otherwise return 'VALID'.";

        $payload = [
            'contents' => [
                [
                    'parts' => [
                        [
                            'inline_data' => [
                                'mime_type' => 'image/jpeg',
                                'data'      => base64_encode($imgBytes),
                            ],
                        ],
                        [
                            'text' => $prompt,
                        ],
                    ],
                ],
            ],
            'generationConfig' => [
                'temperature'     => 0,
                'maxOutputTokens' => 8,
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
            $error = 'Gemini image validation failed; treating image as invalid.';
            return null;
        }

        $json = json_decode($response, true);
        $text = $json['candidates'][0]['content']['parts'][0]['text'] ?? '';
        $text = strtoupper(trim((string) $text));

        if ($text === 'VALID') {
            $is_valid = true;
        } else {
            $is_valid = false;
            $error    = 'Gemini marked the image as invalid.';
            return null;
        }

        // Use the bytes we already downloaded for processing.
        $imgData = $imgBytes;
    }

    // Image processing: resize to 800x800 with white letterbox.
    $tmpDir = BASE_PATH . 'public/tmp/enrich/';
    if (!is_dir($tmpDir)) {
        @mkdir($tmpDir, 0755, true);
    }

    $filename = 'enrich_' . time() . '_' . substr(sha1($image_url . mt_rand()), 0, 8) . '.jpg';
    $fullPath = $tmpDir . $filename;

    // Prefer Intervention\Image if available.
    $processed = false;
    if (file_exists(BASE_PATH . 'vendor/autoload.php')) {
        @require_once BASE_PATH . 'vendor/autoload.php';
    }
    if (class_exists('\Intervention\Image\ImageManagerStatic')) {
        $manager = new \Intervention\Image\ImageManagerStatic();
        $img     = $manager::make(isset($imgData) ? $imgData : $image_url);
        $img->resize(800, 800, function ($constraint) {
            $constraint->aspectRatio();
            $constraint->upsize();
        });
        $canvas = $manager::canvas(800, 800, '#ffffff');
        $canvas->insert($img, 'center');
        $canvas->save($fullPath, 90, 'jpg');
        $processed = true;
    } else {
        // Fallback to basic GD if available.
        $raw = isset($imgData) ? $imgData : @file_get_contents($image_url);
        if ($raw !== false && strlen($raw) > 0) {
            $src = @imagecreatefromstring($raw);
            if ($src) {
                $srcW = imagesx($src);
                $srcH = imagesy($src);
                $targetW = 800;
                $targetH = 800;

                $scale = min($targetW / $srcW, $targetH / $srcH);
                $newW = (int) floor($srcW * $scale);
                $newH = (int) floor($srcH * $scale);

                $dst = imagecreatetruecolor($targetW, $targetH);
                $white = imagecolorallocate($dst, 255, 255, 255);
                imagefilledrectangle($dst, 0, 0, $targetW, $targetH, $white);

                $dstX = (int) floor(($targetW - $newW) / 2);
                $dstY = (int) floor(($targetH - $newH) / 2);
                imagecopyresampled($dst, $src, $dstX, $dstY, 0, 0, $newW, $newH, $srcW, $srcH);

                imagejpeg($dst, $fullPath, 90);
                imagedestroy($src);
                imagedestroy($dst);
                $processed = true;
            }
        }
    }

    if (!$processed) {
        $error = 'Failed to process image.';
        return null;
    }

    // Public URL (assuming /public is web root).
    $publicUrl = '/tmp/enrich/' . $filename;
    return $publicUrl;
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
