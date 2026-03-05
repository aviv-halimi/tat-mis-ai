<?php
/**
 * PO vs Brand Menu matching via Gemini.
 * Parallel batches of 100; matching done by Gemini. Can run in CLI (no web timeout).
 */
set_time_limit(0);

function matchPoToMenuGemini(array $pdf_file_paths, array $po_products, &$debug_log = null, array $po_brands = array(), array $po_categories = array())
{
    $debug_log = $debug_log ?? [];
    $apiKey = getenv('GEMINI_API_KEY') ?: (defined('GEMINI_API_KEY') ? GEMINI_API_KEY : null);
    if (!$apiKey) {
        return null;
    }

    // 1. Extract menu text once (pdftotext)
    $menu_text = '';
    foreach ($pdf_file_paths as $path) {
        if (!file_exists($path)) {
            continue;
        }
        $out = @shell_exec('pdftotext -layout -enc UTF-8 ' . escapeshellarg($path) . ' - 2>/dev/null');
        if ($out) {
            $menu_text .= "--- MENU ---\n" . trim($out) . "\n\n";
        }
    }
    if ($menu_text === '') {
        $debug_log[] = '[SYNC] No menu text extracted from PDFs.';
        return null;
    }

    $brand_names = implode('" or "', array_unique(array_filter(array_column($po_brands, 'brand_name')))) ?: 'brand name';
    $model = (defined('GEMINI_PO_MENU_MODEL') && GEMINI_PO_MENU_MODEL !== '') ? GEMINI_PO_MENU_MODEL : 'gemini-2.0-flash';
    $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . $model . ':generateContent?key=' . urlencode($apiKey);

    $batch_size = 100;
    $concurrency = 4; // Max parallel Gemini requests; avoids rate-limit queuing
    $per_request_timeout = 90; // seconds per request
    $batches = array_chunk($po_products, $batch_size);
    $total_batches = count($batches);
    $debug_log[] = "[SYNC] Gemini: {$total_batches} batch(es) of {$batch_size}, concurrency={$concurrency}.";

    // Primary schema (batch 0): include add_products
    $schema_primary = [
        'type' => 'OBJECT',
        'properties' => [
            'found_po_product_ids' => ['type' => 'ARRAY', 'items' => ['type' => 'INTEGER']],
            'add_products' => [
                'type' => 'ARRAY',
                'items' => [
                    'type' => 'OBJECT',
                    'properties' => [
                        'name' => ['type' => 'STRING'],
                        'price' => ['type' => 'NUMBER'],
                        'brand_id' => ['type' => 'INTEGER', 'nullable' => true],
                        'category_id' => ['type' => 'INTEGER', 'nullable' => true],
                    ],
                    'required' => ['name', 'price'],
                ],
            ],
        ],
        'required' => ['found_po_product_ids', 'add_products'],
    ];
    // Secondary schema (batch 1+): IDs only — smaller output, faster generation
    $schema_ids_only = [
        'type' => 'OBJECT',
        'properties' => [
            'found_po_product_ids' => ['type' => 'ARRAY', 'items' => ['type' => 'INTEGER']],
        ],
        'required' => ['found_po_product_ids'],
    ];

    $all_found_ids = [];
    $all_add_products = [];
    $seen_add_names = [];

    // Process in waves of $concurrency to avoid rate limits
    foreach (array_chunk($batches, $concurrency, true) as $wave) {
        $mh = curl_multi_init();
        $wave_handles = [];

        foreach ($wave as $index => $batch) {
            $is_primary = ($index === 0);
            $batch_json = json_encode($batch);
            $prompt = "Match PO lines to the menu. Ignore \"{$brand_names}\" prefix. "
                . "Return found_po_product_ids for each PO line that exists on the menu (same strain + category). "
                . ($is_primary ? "Return add_products for menu items not on the PO (name, price, brand_id, category_id when possible). " : '')
                . "PO batch (JSON): {$batch_json}";

            $payload = [
                'contents' => [
                    ['parts' => [
                        ['text' => $menu_text],
                        ['text' => $prompt],
                    ]],
                ],
                'generationConfig' => [
                    'temperature' => 0.0,
                    'maxOutputTokens' => $is_primary ? 8192 : 4096,
                    'responseMimeType' => 'application/json',
                    'responseSchema' => $is_primary ? $schema_primary : $schema_ids_only,
                ],
            ];

            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($payload),
                CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                CURLOPT_TIMEOUT => $per_request_timeout,
                CURLOPT_CONNECTTIMEOUT => 15,
            ]);
            curl_multi_add_handle($mh, $ch);
            $wave_handles[$index] = $ch;
        }

        // Run this wave
        $active = null;
        do {
            $mrc = curl_multi_exec($mh, $active);
            if ($mrc === CURLM_CALL_MULTI_PERFORM) {
                continue;
            }
            if ($mrc !== CURLM_OK) {
                break;
            }
            if ($active > 0) {
                curl_multi_select($mh, 1.0);
            }
        } while ($active > 0);

        foreach ($wave_handles as $index => $ch) {
            $batch_num = $index + 1;
            $raw = curl_multi_getcontent($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curl_err = curl_error($ch);

            if ($curl_err) {
                $debug_log[] = "[SYNC] Batch {$batch_num} cURL error: {$curl_err}";
            } elseif ($http_code !== 200) {
                $debug_log[] = "[SYNC] Batch {$batch_num} HTTP {$http_code}: " . substr($raw, 0, 300);
            } else {
                $res = is_string($raw) ? json_decode($raw, true) : null;
                $text = isset($res['candidates'][0]['content']['parts'][0]['text'])
                    ? $res['candidates'][0]['content']['parts'][0]['text']
                    : '';
                $finishReason = $res['candidates'][0]['finishReason'] ?? '';
                $debug_log[] = "[SYNC] Batch {$batch_num} response (" . strlen($text) . " chars, finishReason={$finishReason}):";
                $debug_log[] = $text !== '' ? $text : '(empty)';

                $data = is_string($text) && $text !== '' ? json_decode($text, true) : null;
                if (!is_array($data)) {
                    $data = _gemini_parse_json_fallback($text);
                }

                if (!empty($data['found_po_product_ids']) && is_array($data['found_po_product_ids'])) {
                    foreach ($data['found_po_product_ids'] as $id) {
                        $id = (int) $id;
                        if ($id > 0) {
                            $all_found_ids[] = $id;
                        }
                    }
                }

                if (!empty($data['add_products']) && is_array($data['add_products'])) {
                    foreach ($data['add_products'] as $item) {
                        if (!is_array($item)) {
                            continue;
                        }
                        $name = isset($item['name']) ? trim((string) $item['name']) : '';
                        if ($name === '') {
                            continue;
                        }
                        $key = strtolower($name);
                        if (isset($seen_add_names[$key])) {
                            continue;
                        }
                        $seen_add_names[$key] = true;
                        $row = [
                            'name' => $name,
                            'price' => isset($item['price']) && is_numeric($item['price']) ? (float) $item['price'] : 0,
                        ];
                        if (isset($item['brand_id']) && is_numeric($item['brand_id'])) {
                            $row['brand_id'] = (int) $item['brand_id'];
                        }
                        if (isset($item['category_id']) && is_numeric($item['category_id'])) {
                            $row['category_id'] = (int) $item['category_id'];
                        }
                        $all_add_products[] = $row;
                    }
                }
            }

            curl_multi_remove_handle($mh, $ch);
            curl_close($ch);
        }
        curl_multi_close($mh);
    }

    $all_ids = array_column($po_products, 'po_product_id');
    $disable_ids = array_values(array_diff($all_ids, $all_found_ids));

    return [
        'disable_po_product_ids' => $disable_ids,
        'add_products' => $all_add_products,
    ];
}

function _gemini_parse_json_fallback($text)
{
    if (!is_string($text) || trim($text) === '') {
        return null;
    }
    $text = preg_replace('/^[\s\S]*?(\{[\s\S]*\})[\s\S]*$/s', '$1', trim($text));
    $text = preg_replace('/```\s*json\s*|\s*```/i', '', $text);
    $decoded = json_decode($text, true);
    if (is_array($decoded)) {
        return $decoded;
    }
    if (preg_match('/"found_po_product_ids"\s*:\s*\[([\d,\s]*)/s', $text, $m)) {
        $ids = [];
        if (preg_match_all('/\d+/', $m[1], $mm)) {
            foreach ($mm[0] as $id) {
                $id = (int) $id;
                if ($id > 0) {
                    $ids[] = $id;
                }
            }
        }
        $decoded = ['found_po_product_ids' => $ids, 'add_products' => []];
        if (preg_match('/"add_products"\s*:\s*(\[[\s\S]*?\])\s*[,}]/s', $text, $addM)) {
            $arr = json_decode($addM[1], true);
            if (is_array($arr)) {
                $decoded['add_products'] = $arr;
            }
        }
        return $decoded;
    }
    return null;
}
