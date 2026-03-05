<?php
/**
 * PO vs Brand Menu matching via Gemini.
 * Single request or sequential batches only (no parallel) to avoid rate limits and indefinite hangs.
 */
set_time_limit(0);

function matchPoToMenuGemini(array $pdf_file_paths, array $po_products, &$debug_log = null, array $po_brands = array(), array $po_categories = array())
{
    $debug_log = $debug_log ?? [];
    $apiKey = getenv('GEMINI_API_KEY') ?: (defined('GEMINI_API_KEY') ? GEMINI_API_KEY : null);
    if (!$apiKey) {
        return null;
    }

    // 1. Extract menu text once (no raw PDFs to avoid slow/oversized requests)
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

    // 2. One batch per chunk; run sequentially to avoid rate limits
    $batch_size = 400;
    $batches = array_chunk($po_products, $batch_size);
    $total_batches = count($batches);
    $all_found_ids = [];
    $all_add_products = [];

    $schema = [
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

    foreach ($batches as $index => $batch) {
        $batch_num = $index + 1;
        $debug_log[] = "[SYNC] Gemini batch {$batch_num}/{$total_batches} (" . count($batch) . " products).";
        $batch_json = json_encode($batch);

        $prompt = "Match PO lines to the menu. Ignore \"{$brand_names}\" prefix. "
            . "Return found_po_product_ids for each PO line that exists on the menu (same strain + category). "
            . "Return add_products for menu items not on the PO (name, price, brand_id, category_id when possible). "
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
                'maxOutputTokens' => 16384,
                'responseMimeType' => 'application/json',
                'responseSchema' => $schema,
            ],
        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT => 180,
        ]);

        $raw = curl_exec($ch);
        $err = curl_error($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($err || $raw === false) {
            $debug_log[] = "[SYNC] Batch {$batch_num} cURL error: " . ($err ?: 'empty response');
            continue;
        }
        if ($code !== 200) {
            $debug_log[] = "[SYNC] Batch {$batch_num} HTTP {$code}: " . substr($raw, 0, 500);
            continue;
        }

        $res = json_decode($raw, true);
        $candidate = $res['candidates'][0] ?? null;
        $text = $candidate['content']['parts'][0]['text'] ?? '';
        $finishReason = $candidate['finishReason'] ?? '';

        $text_len = strlen($text);
        $debug_log[] = "[SYNC] Batch {$batch_num} response: {$text_len} chars, finishReason=" . ($finishReason ?: 'UNSPECIFIED');
        if ($text_len > 0) {
            $head = substr($text, 0, 500);
            $tail = $text_len > 1000 ? substr($text, -500) : '';
            $debug_log[] = "[SYNC] Batch {$batch_num} response START: " . $head;
            if ($tail !== '') {
                $debug_log[] = "[SYNC] Batch {$batch_num} response END: " . $tail;
            }
        }
        if ($finishReason === 'MAX_TOKENS') {
            $debug_log[] = "[SYNC] Batch {$batch_num} WARNING: Response was truncated (MAX_TOKENS). Consider smaller batch or check parsed counts.";
        }

        if ($text === '') {
            $debug_log[] = "[SYNC] Batch {$batch_num} empty response.";
            continue;
        }

        $data = json_decode($text, true);
        $used_fallback = false;
        if (!is_array($data)) {
            $data = _gemini_parse_json_fallback($text);
            $used_fallback = is_array($data);
        }
        if (!is_array($data)) {
            $debug_log[] = "[SYNC] Batch {$batch_num} invalid JSON. Last 300 chars: " . substr($text, -300);
            continue;
        }
        if ($used_fallback) {
            $debug_log[] = "[SYNC] Batch {$batch_num} used fallback JSON parse (response may be truncated).";
        }

        $num_found = isset($data['found_po_product_ids']) && is_array($data['found_po_product_ids']) ? count($data['found_po_product_ids']) : 0;
        $num_add = isset($data['add_products']) && is_array($data['add_products']) ? count($data['add_products']) : 0;
        $debug_log[] = "[SYNC] Batch {$batch_num} parsed: found_po_product_ids={$num_found}, add_products={$num_add}";

        if (!empty($data['found_po_product_ids']) && is_array($data['found_po_product_ids'])) {
            foreach ($data['found_po_product_ids'] as $id) {
                $id = (int) $id;
                if ($id > 0) {
                    $all_found_ids[] = $id;
                }
            }
        }
        // Only take add_products from first batch to avoid duplicates
        if ($index === 0 && !empty($data['add_products']) && is_array($data['add_products'])) {
            foreach ($data['add_products'] as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $name = isset($item['name']) ? trim((string) $item['name']) : '';
                if ($name === '') {
                    continue;
                }
                $row = [
                    'name' => $name,
                    'price' => isset($item['price']) && is_numeric($item['price']) ? (float) $item['price'] : 0,
                ];
                if (isset($item['brand_id']) && (is_int($item['brand_id']) || (is_string($item['brand_id']) && is_numeric($item['brand_id'])))) {
                    $row['brand_id'] = (int) $item['brand_id'];
                }
                if (isset($item['category_id']) && (is_int($item['category_id']) || (is_string($item['category_id']) && is_numeric($item['category_id'])))) {
                    $row['category_id'] = (int) $item['category_id'];
                }
                $all_add_products[] = $row;
            }
        }
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
