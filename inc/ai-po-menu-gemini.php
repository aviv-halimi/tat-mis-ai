<?php
/**
 * PO vs Brand Menu matching - NUCLEAR SPEED EDITION
 * Parallel execution + Pruned Schema (No explanations, just IDs)
 */

set_time_limit(0); 

function matchPoToMenuGemini(array $pdf_file_paths, array $po_products, &$debug_log = null, array $po_brands = array(), array $po_categories = array())
{
    $debug_log = $debug_log ?? [];
    $apiKey = getenv('GEMINI_API_KEY') ?: (defined('GEMINI_API_KEY') ? GEMINI_API_KEY : null);
    if (!$apiKey) return null;

    // 1. FAST TEXT EXTRACTION (Crucial: sending raw PDFs to 5 threads will 504)
    $menu_text = "";
    foreach ($pdf_file_paths as $path) {
        if (!file_exists($path)) continue;
        $menu_text .= @shell_exec("pdftotext -layout -enc UTF-8 " . escapeshellarg($path) . " - 2>/dev/null") . "\n\n";
    }

    $brand_names = implode('" or "', array_unique(array_filter(array_column($po_brands, 'brand_name')))) ?: '710 Labs';
    $batches = array_chunk($po_products, 100); 
    $total_batches = count($batches);
    
    $mh = curl_multi_init();
    $requests = [];

    foreach ($batches as $index => $batch) {
        $batch_json = json_encode($batch);
        $is_primary = ($index === 0);

        // NUCLEAR PRUNING: We only ask for the IDs. No logs, no explanations.
        $schema = [
            'type' => 'OBJECT',
            'properties' => [
                'found_po_product_ids' => ['type' => 'ARRAY', 'items' => ['type' => 'INTEGER']]
            ],
            'required' => ['found_po_product_ids']
        ];
        
        // Only the first thread handles the "Add New" logic to save time on others
        if ($is_primary) {
            $schema['properties']['add_products'] = [
                'type' => 'ARRAY',
                'items' => [
                    'type' => 'OBJECT',
                    'properties' => [
                        'name' => ['type' => 'STRING'],
                        'price' => ['type' => 'NUMBER']
                    ],
                    'required' => ['name', 'price']
                ]
            ];
            $schema['required'][] = 'add_products';
        }

        $payload = json_encode([
            'contents' => [['parts' => [['text' => $menu_text], ['text' => "Match Batch: $batch_json"]]]],
            'generationConfig' => [
                'temperature' => 0.0,
                'responseMimeType' => 'application/json',
                'responseSchema' => $schema
            ]
        ]);

        $ch = curl_init("https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=$apiKey");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT => 45 // Tight timeout for speed
        ]);

        curl_multi_add_handle($mh, $ch);
        $requests[$index] = $ch;
    }

    // 2. EXECUTE ALL IN PARALLEL
    $active = null;
    do {
        $mrc = curl_multi_exec($mh, $active);
    } while ($active > 0);

    // 3. MERGE RESULTS
    $all_found_ids = [];
    $all_add_products = [];

    foreach ($requests as $index => $ch) {
        $raw_response = curl_multi_getcontent($ch);
        $res_array = json_decode($raw_response, true);
        $clean_json = json_decode($res_array['candidates'][0]['content']['parts'][0]['text'] ?? '{}', true);

        if (!empty($clean_json['found_po_product_ids'])) {
            $all_found_ids = array_merge($all_found_ids, $clean_json['found_po_product_ids']);
        }
        if ($index === 0 && !empty($clean_json['add_products'])) {
            $all_add_products = $clean_json['add_products'];
        }
        
        curl_multi_remove_handle($mh, $ch);
        curl_close($ch);
    }
    curl_multi_close($mh);

    // 4. FINAL DIFF
    $original_ids = array_column($po_products, 'po_product_id');
    $disable_ids = array_values(array_diff($original_ids, $all_found_ids));

    return [
        'disable_po_product_ids' => $disable_ids,
        'add_products' => $all_add_products
    ];
}