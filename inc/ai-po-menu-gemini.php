<?php
/**
 * PO vs Brand Menu matching via Gemini 2.0 Flash.
 * Logic: Extract Menu from AI -> Match locally in PHP.
 * Benefits: Lightning fast (~10s), solves "Lunar Z" space issues, no token cutoffs.
 */
set_time_limit(120);

function matchPoToMenuGemini(array $pdf_file_paths, array $po_products, &$debug_log = null, array $po_brands = array(), array $po_categories = array())
{
    $debug_log = $debug_log ?? [];
    $apiKey = getenv('GEMINI_API_KEY') ?: (defined('GEMINI_API_KEY') ? GEMINI_API_KEY : null);
    if (!$apiKey) return null;

    // 1. Convert PDFs to Text
    $menu_text = '';
    foreach ($pdf_file_paths as $path) {
        if (!file_exists($path)) continue;
        $out = @shell_exec('pdftotext -layout -enc UTF-8 ' . escapeshellarg($path) . ' - 2>/dev/null');
        if ($out) $menu_text .= "--- MENU START ---\n" . trim($out) . "\n--- MENU END ---\n\n";
    }

    if (empty($menu_text)) {
        $debug_log[] = '[SYNC] No menu text extracted.';
        return null;
    }

    // 2. Extract Clean Menu via Gemini
    $model = (defined('GEMINI_PO_MENU_MODEL') && GEMINI_PO_MENU_MODEL !== '') ? GEMINI_PO_MENU_MODEL : 'gemini-2.0-flash';
    
    $schema = [
        'type' => 'OBJECT',
        'properties' => [
            'extracted_menu' => [
                'type' => 'ARRAY',
                'items' => [
                    'type' => 'OBJECT',
                    'properties' => [
                        'name' => ['type' => 'STRING'],
                        'price' => ['type' => 'NUMBER']
                    ],
                    'required' => ['name', 'price']
                ]
            ]
        ],
        'required' => ['extracted_menu']
    ];

    $prompt = "Extract every product name and price from the menu. Ignore genetics and descriptions. Return ONLY a valid JSON list.";

    $payload = [
        'contents' => [['parts' => [['text' => $menu_text], ['text' => $prompt]]]],
        'generationConfig' => [
            'temperature' => 0.0,
            'responseMimeType' => 'application/json',
            'responseSchema' => $schema
        ]
    ];

    $ch = curl_init("https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT => 60
    ]);

    $raw = curl_exec($ch);
    $res = json_decode($raw, true);
    $ai_data = json_decode($res['candidates'][0]['content']['parts'][0]['text'] ?? '{}', true);
    curl_close($ch);

    $menu_items = $ai_data['extracted_menu'] ?? [];
    if (empty($menu_items)) {
        $debug_log[] = '[SYNC] Gemini failed to extract products from menu.';
        return null;
    }

    // 3. Match PO to extracted Menu in PHP (The "Lunar Z" Fix)
    $found_po_ids = [];
    $matched_menu_names = [];

    foreach ($po_products as $po) {
        // Normalize PO name: lowercase, remove "710 Labs", remove all spaces
        $clean_po = strtolower(str_replace(['710 Labs', ' '], '', $po['product_name']));
        
        foreach ($menu_items as $menu) {
            $clean_menu = strtolower(str_replace(' ', '', $menu['name']));
            
            // Check if names overlap (e.g. "lunarz" matches "lunarzbadder")
            if ($clean_po !== '' && (strpos($clean_po, $clean_menu) !== false || strpos($clean_menu, $clean_po) !== false)) {
                $found_po_ids[] = $po['po_product_id'];
                $matched_menu_names[] = strtolower(trim($menu['name']));
                break;
            }
        }
    }

    // 4. Identify Add Products (Items on menu NOT matched to PO)
    $add_products = [];
    foreach ($menu_items as $menu) {
        if (!in_array(strtolower(trim($menu['name'])), $matched_menu_names)) {
            $add_products[] = [
                'name' => $menu['name'],
                'price' => $menu['price'],
                'brand_id' => $po_brands[0]['brand_id'] ?? null // Default to main brand
            ];
        }
    }

    // 5. Result
    $all_ids = array_column($po_products, 'po_product_id');
    $disable_ids = array_values(array_diff($all_ids, $found_po_ids));

    $debug_log[] = "[SYNC] Gemini Response: " . strlen($raw) . " chars. Local matches: " . count($found_po_ids);

    return [
        'disable_po_product_ids' => $disable_ids,
        'add_products' => $add_products
    ];
}