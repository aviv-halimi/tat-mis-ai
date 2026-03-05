<?php
/**
 * PO vs Brand Menu matching via Gemini 2.0 Flash.
 * Optimized with: 250-item Batching, Strict Mapping, and Sequential Processing.
 */
set_time_limit(0);

function matchPoToMenuGemini(array $pdf_file_paths, array $po_products, &$debug_log = null, array $po_brands = array(), array $po_categories = array())
{
    $debug_log = $debug_log ?? [];
    $apiKey = getenv('GEMINI_API_KEY') ?: (defined('GEMINI_API_KEY') ? GEMINI_API_KEY : null);
    if (!$apiKey) return null;

    // 1. Extract menu text once
    $menu_text = '';
    foreach ($pdf_file_paths as $path) {
        if (!file_exists($path)) continue;
        $out = @shell_exec('pdftotext -layout -enc UTF-8 ' . escapeshellarg($path) . ' - 2>/dev/null');
        if ($out) $menu_text .= "--- MENU ---\n" . trim($out) . "\n\n";
    }

    if ($menu_text === '') {
        $debug_log[] = '[SYNC] No menu text extracted from PDFs.';
        return null;
    }

    $brand_names = implode('" or "', array_unique(array_filter(array_column($po_brands, 'brand_name')))) ?: '710 Labs';
    $model = (defined('GEMINI_PO_MENU_MODEL') && GEMINI_PO_MENU_MODEL !== '') ? GEMINI_PO_MENU_MODEL : 'gemini-2.0-flash';
    $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . $model . ':generateContent?key=' . urlencode($apiKey);

    // 2. Batch by 250 for the optimal balance of speed and accuracy
    $batch_size = 250;
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
        $batch_json = json_encode($batch);

        // Only ask for new products in the first batch to save output tokens
        $addTask = ($index === 0) 
            ? "Also, extract all items from the menu NOT on this PO into 'add_products'." 
            : "IMPORTANT: Set 'add_products' to [] (empty array) for this batch.";

        $prompt = <<<PROMPT
### TASK
Reconcile this PO batch against the menu. 
$addTask

### STRICT MATCHING RULES
- **Normalization:** Ignore "{$brand_names}" prefix. "Lunar Z" and "LunarZ" are EXACT matches.
- **Category Map:** * PO "Solventless Extracts" = Menu "BADDER", "ROSIN", "SAUCE", "THUMB PRINT".
  * PO "Vape Carts .5g" / "AIO" = Menu "PERSY POD", "VAPE", "ALL IN ONE".
  * PO "Flowers" = Menu "FLOWER", "EIGHTHS", "3.5G", "14G".
- **Decision:** Include the po_product_id in 'found_po_product_ids' only if the Strain and Functional Category exist on the menu.

### PO BATCH (JSON)
{$batch_json}
PROMPT;

        $payload = [
            'system_instruction' => ['parts' => [['text' => 'You are a precision inventory auditor. Match by strain and functional category. Return valid JSON only.']]],
            'contents' => [
                ['parts' => [
                    ['text' => $menu_text],
                    ['text' => $prompt],
                ]],
            ],
            'generationConfig' => [
                'temperature' => 0.0,
                'maxOutputTokens' => 8192,
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
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($code !== 200) {
            $debug_log[] = "[SYNC] Batch {$batch_num} failed with HTTP {$code}";
            continue;
        }

        $res = json_decode($raw, true);
        $text = $res['candidates'][0]['content']['parts'][0]['text'] ?? '{}';
        $data = json_decode($text, true);

        // Process found IDs
        if (!empty($data['found_po_product_ids'])) {
            foreach ($data['found_po_product_ids'] as $id) {
                $all_found_ids[] = (int)$id;
            }
        }

        // Process Add Products
        if (!empty($data['add_products'])) {
            foreach ($data['add_products'] as $item) {
                $all_add_products[] = $item;
            }
        }
    }

    // Final PHP Reconciliation
    $all_po_ids = array_column($po_products, 'po_product_id');
    $disable_ids = array_values(array_diff($all_po_ids, $all_found_ids));

    return [
        'disable_po_product_ids' => $disable_ids,
        'add_products' => $all_add_products,
    ];
}