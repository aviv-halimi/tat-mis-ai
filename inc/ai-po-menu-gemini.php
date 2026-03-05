<?php
/**
 * PO vs Brand Menu matching via Google Gemini 2.0 Flash.
 * Optimized with: Smart Batching, Response Schema, and Category Normalization.
 */

// ... (Keep your existing defined constants and _gemini_extract_json_array function) ...

function matchPoToMenuGemini(array $pdf_file_paths, array $po_products, &$debug_log = null, array $po_brands = array(), array $po_categories = array())
{
    $debug_log = $debug_log ?? [];
    $apiKey = getenv('GEMINI_API_KEY') ?: (defined('GEMINI_API_KEY') ? GEMINI_API_KEY : null);
    if (!$apiKey) return null;

    // 1. Convert PDFs to Text (Highly Recommended for Speed)
    $menu_text = "";
    foreach ($pdf_file_paths as $path) {
        if (!file_exists($path)) continue;
        $escaped = escapeshellarg($path);
        $text = @shell_exec("pdftotext -layout -enc UTF-8 {$escaped} - 2>/dev/null");
        if ($text) $menu_text .= "--- MENU START ---\n" . trim($text) . "\n--- MENU END ---\n\n";
    }

    // Fallback: If text extraction fails, send raw PDF (Slower)
    $inline_pdf_parts = [];
    if (empty($menu_text)) {
        foreach ($pdf_file_paths as $path) {
            $inline_pdf_parts[] = [
                'inline_data' => [
                    'mime_type' => 'application/pdf',
                    'data' => base64_encode(file_get_contents($path))
                ]
            ];
        }
    }

    // 2. Reconciliation Logic Constants
    $brand_names = implode('" or "', array_unique(array_filter(array_column($po_brands, 'brand_name')))) ?: '710 Labs';
    $all_found_ids = [];
    $all_add_products = [];
    
    // Split into batches of 100 to prevent token cutoffs
    $batches = array_chunk($po_products, 100); 

    foreach ($batches as $index => $batch) {
        $batch_json = json_encode($batch);
        $prompt = <<<PROMPT
### TASK
Verify which PO items exist on the vendor menu. 
Normalization: Ignore "{$brand_names}" prefix. "Lunar Z" and "LunarZ" are an EXACT MATCH.

### CATEGORY MAPPING
- PO "Solventless Extracts" = Menu "BADDER", "ROSIN", "SAUCE", "THUMB PRINT".
- PO "Vape Carts .5g" / "AIO" = Menu "PERSY POD", "SOLVENTLESS PODS", "ALL IN ONE".
- PO "Flowers" = Menu "FLOWER", "EIGHTHS", "3.5G", "14G".

### INPUT
PO Batch: {$batch_json}
PROMPT;

        $contents_parts = !empty($menu_text) ? [['text' => $menu_text]] : $inline_pdf_parts;
        $contents_parts[] = ['text' => $prompt];

        // 3. Define Schema (found_po_product_ids FIRST is critical)
        $response_schema = [
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
                            'category_id' => ['type' => 'INTEGER', 'nullable' => true]
                        ],
                        'required' => ['name', 'price']
                    ]
                ]
            ],
            'required' => ['found_po_product_ids', 'add_products']
        ];

        // 4. API Call
        $model = defined('GEMINI_PO_MENU_MODEL') && GEMINI_PO_MENU_MODEL !== '' ? GEMINI_PO_MENU_MODEL : 'gemini-2.0-flash';
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";

        $payload = [
            'system_instruction' => ['parts' => [['text' => 'Precision Auditor. Return valid JSON only.']]],
            'contents' => [['parts' => $contents_parts]],
            'generationConfig' => [
                'temperature' => 0.0,
                'responseMimeType' => 'application/json',
                'responseSchema' => $response_schema
            ]
        ];

        // Execute Request
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT => 90
        ]);

        $response = curl_exec($ch);
        $result = json_decode($response, true);
        $text = $result['candidates'][0]['content']['parts'][0]['text'] ?? '{}';
        $data = json_decode($text, true);

        // Aggregate Results
        if (!empty($data['found_po_product_ids'])) {
            $all_found_ids = array_merge($all_found_ids, $data['found_po_product_ids']);
        }
        // Only add products from the first batch to avoid 400+ duplicates
        if ($index === 0 && !empty($data['add_products'])) {
            $all_add_products = $data['add_products'];
        }
        
        curl_close($ch);
    }

    // 5. Final Reconciliation in PHP
    $original_ids = array_column($po_products, 'po_product_id');
    $disable_ids = array_values(array_diff($original_ids, $all_found_ids));

    $debug_log[] = "[SYNC] Done. Kept: " . count($all_found_ids) . " | Disabled: " . count($disable_ids);

    return [
        'disable_po_product_ids' => $disable_ids,
        'add_products' => $all_add_products
    ];
}