<?php
/**
 * PO vs Brand Menu matching via Google Gemini 2.0 Flash.
 * Optimized with: curl_multi (Parallelism) and CLI Progress Tracking.
 */

set_time_limit(0); // Ensure CLI doesn't timeout

function matchPoToMenuGemini(array $pdf_file_paths, array $po_products, &$debug_log = null, array $po_brands = array(), array $po_categories = array())
{
    $debug_log = $debug_log ?? [];
    $apiKey = getenv('GEMINI_API_KEY') ?: (defined('GEMINI_API_KEY') ? GEMINI_API_KEY : null);
    if (!$apiKey) return null;

    // 1. Fast Text Extraction
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

    echo "\n🚀 Initializing parallel sync for " . count($po_products) . " items ($total_batches batches)...\n";

    foreach ($batches as $index => $batch) {
        $batch_json = json_encode($batch);
        
        // Batch 0 handles 'add_products', others handle 'matching' only to save speed
        $is_primary = ($index === 0);
        $task = $is_primary ? "Verify matches AND list new products." : "Verify matches ONLY.";

        $current_schema = [
            'type' => 'OBJECT',
            'properties' => [
                'found_po_product_ids' => ['type' => 'ARRAY', 'items' => ['type' => 'INTEGER']]
            ],
            'required' => ['found_po_product_ids']
        ];
        
        if ($is_primary) {
            $current_schema['properties']['add_products'] = [
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
            $current_schema['required'][] = 'add_products';
        }

        $payload = json_encode([
            'contents' => [['parts' => [['text' => $menu_text], ['text' => "### TASK: $task\nBatch: $batch_json"]]]],
            'generationConfig' => [
                'temperature' => 0.0,
                'responseMimeType' => 'application/json',
                'responseSchema' => $current_schema
            ]
        ]);

        $ch = curl_init("https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=$apiKey");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT => 60
        ]);

        curl_multi_add_handle($mh, $ch);
        $requests[$index] = ['handle' => $ch, 'done' => false];
    }

    // 2. Parallel Execution with CLI Feedback
    $active = null;
    do {
        $status = curl_multi_exec($mh, $active);
        
        // Track which handles finished in this loop
        while ($info = curl_multi_info_read($mh)) {
            foreach ($requests as $idx => $req) {
                if ($req['handle'] === $info['handle'] && !$req['done']) {
                    $requests[$idx]['done'] = true;
                    echo " ✅ Batch " . ($idx + 1) . " completed.\n";
                }
            }
        }
        usleep(100000); // 0.1s sleep to prevent CPU spike
    } while ($active && $status == CURLM_OK);

    // 3. Merge Results
    $all_found_ids = [];
    $all_add_products = [];

    foreach ($requests as $index => $req) {
        $response = curl_multi_getcontent($req['handle']);
        $result = json_decode($response, true);
        $data = json_decode($result['candidates'][0]['content']['parts'][0]['text'] ?? '{}', true);

        if (!empty($data['found_po_product_ids'])) {
            $all_found_ids = array_merge($all_found_ids, $data['found_po_product_ids']);
        }
        if ($index === 0 && !empty($data['add_products'])) {
            $all_add_products = $data['add_products'];
        }
        
        curl_multi_remove_handle($mh, $req['handle']);
        curl_close($req['handle']);
    }
    curl_multi_close($mh);

    $original_ids = array_column($po_products, 'po_product_id');
    $disable_ids = array_values(array_diff($original_ids, $all_found_ids));

    echo "\n✨ Total Processed: " . count($po_products) . "\n";
    echo "👍 Kept: " . count($all_found_ids) . "\n";
    echo "👎 Disabled: " . count($disable_ids) . "\n\n";

    return [
        'disable_po_product_ids' => $disable_ids,
        'add_products' => $all_add_products
    ];
}