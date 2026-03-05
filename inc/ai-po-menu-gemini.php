<?php
/**
 * PO vs Brand Menu matching - High Speed Single-Pass Version.
 * Estimated Runtime: 25-40 seconds.
 */
set_time_limit(180);

function matchPoToMenuGemini(array $pdf_file_paths, array $po_products, &$debug_log = null, array $po_brands = array(), array $po_categories = array())
{
    $debug_log = $debug_log ?? [];
    $apiKey = getenv('GEMINI_API_KEY') ?: (defined('GEMINI_API_KEY') ? GEMINI_API_KEY : null);
    if (!$apiKey) return null;

    // 1. Faster Extraction
    $menu_text = '';
    foreach ($pdf_file_paths as $path) {
        $menu_text .= "--- MENU ---\n" . shell_exec('pdftotext -layout ' . escapeshellarg($path) . ' -') . "\n";
    }

    // 2. COMPRESS THE PO LIST (This is the speed secret)
    // Instead of heavy JSON, we send a tiny string: "ID|Name|Category"
    $compressed_po = "";
    foreach ($po_products as $p) {
        $compressed_po .= "{$p['po_product_id']}|{$p['product_name']}|{$p['category_name']}\n";
    }

    $brand_names = implode('/', array_unique(array_filter(array_column($po_brands, 'brand_name')))) ?: '710 Labs';

    $prompt = <<<PROMPT
### TASK
Reconcile the PO list against the menu. 
Normalization: Ignore "{$brand_names}". "Lunar Z" = "LunarZ". 

### CATEGORY MAP
- PO "Solventless Extracts" = Menu "BADDER", "ROSIN", "SAUCE", "THUMB PRINT".
- PO "Vape Carts .5g" / "AIO" = Menu "PERSY POD", "VAPE", "ALL IN ONE".

### PO DATA (Format: ID|Name|Category)
{$compressed_po}
PROMPT;

    $payload = [
        'contents' => [['parts' => [['text' => $menu_text], ['text' => $prompt]]]],
        'generationConfig' => [
            'temperature' => 0.0,
            'maxOutputTokens' => 8192,
            'responseMimeType' => 'application/json',
            'responseSchema' => [
                'type' => 'OBJECT',
                'properties' => [
                    'found_po_product_ids' => ['type' => 'ARRAY', 'items' => ['type' => 'INTEGER']],
                    'add_products' => ['type' => 'ARRAY', 'items' => ['type' => 'OBJECT', 'properties' => ['name' => ['type' => 'STRING'], 'price' => ['type' => 'NUMBER']]]]
                ]
            ],
        ],
    ];

    // 3. Single cURL Call (No loop)
    $ch = curl_init("https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=$apiKey");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT => 120, // 2 minutes max
    ]);

    $raw = curl_exec($ch);
    $res = json_decode($raw, true);
    $data = json_decode($res['candidates'][0]['content']['parts'][0]['text'] ?? '{}', true);

    $found_ids = $data['found_po_product_ids'] ?? [];
    $all_ids = array_column($po_products, 'po_product_id');

    return [
        'disable_po_product_ids' => array_values(array_diff($all_ids, $found_ids)),
        'add_products' => $data['add_products'] ?? []
    ];
}