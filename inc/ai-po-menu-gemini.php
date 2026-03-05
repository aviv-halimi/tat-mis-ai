<?php
/**
 * High-Speed Menu Sync (Extract-Only + PHP Fuzzy Matching)
 * Goal: Sub-30 second response for 350+ items with typo tolerance.
 */
set_time_limit(180);

function matchPoToMenuGemini(array $pdf_file_paths, array $po_products, &$debug_log = null, array $po_brands = array(), array $po_categories = array())
{
    $debug_log = $debug_log ?? [];
    $apiKey = getenv('GEMINI_API_KEY') ?: (defined('GEMINI_API_KEY') ? GEMINI_API_KEY : null);
    if (!$apiKey) return null;

    // 1. RAW TEXT EXTRACTION
    $menu_text = '';
    foreach ($pdf_file_paths as $path) {
        $menu_text .= @shell_exec('pdftotext -layout -enc UTF-8 ' . escapeshellarg($path) . ' -');
    }

    if (empty(trim($menu_text))) {
        $debug_log[] = '[SYNC] Failed to extract text from PDFs.';
        return null;
    }

    // 2. MINIMALIST EXTRACTION PROMPT
    // We only ask for the menu items to keep the AI response fast.
    $prompt = "List every product name and price from this menu as a JSON array 'm' with keys 'n' (name) and 'p' (price).";

    $payload = [
        'contents' => [['parts' => [['text' => $menu_text . "\n\n" . $prompt]]]],
        'generationConfig' => [
            'temperature' => 0.0,
            'maxOutputTokens' => 4000,
            'responseMimeType' => 'application/json',
            'responseSchema' => [
                'type' => 'OBJECT',
                'properties' => [
                    'm' => [
                        'type' => 'ARRAY',
                        'items' => [
                            'type' => 'OBJECT',
                            'properties' => [
                                'n' => ['type' => 'STRING'],
                                'p' => ['type' => 'NUMBER']
                            ]
                        ]
                    ]
                ]
            ]
        ]
    ];

    $model = 'gemini-2.0-flash'; 
    $ch = curl_init("https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT => 45 
    ]);

    $raw = curl_exec($ch);
    $res = json_decode($raw, true);
    $ai_json = json_decode($res['candidates'][0]['content']['parts'][0]['text'] ?? '{}', true);
    curl_close($ch);

    $menu_items = $ai_json['m'] ?? [];

    // 3. NUANCED PHP MATCHING
    $found_ids = [];
    $add_products = [];
    $matched_menu_keys = [];

    foreach ($po_products as $po) {
        // Strip brand prefix and spaces for strict match
        $clean_po = strtolower(str_replace(['710 Labs', ' '], '', $po['product_name']));
        
        foreach ($menu_items as $m) {
            $clean_menu = strtolower(str_replace(' ', '', $m['n']));
            
            // A. Strict Space-Agnostic Match (Lunar Z vs LunarZ)
            if ($clean_po !== '' && (strpos($clean_po, $clean_menu) !== false || strpos($clean_menu, $clean_po) !== false)) {
                $found_ids[] = $po['po_product_id'];
                $matched_menu_keys[] = $clean_menu;
                break;
            }

            // B. Nuanced Typo Check (Levenshtein)
            // If the names are long enough and very similar, count as a match.
            if (strlen($clean_po) > 10 && levenshtein($clean_po, $clean_menu) <= 2) {
                $found_ids[] = $po['po_product_id'];
                $matched_menu_keys[] = $clean_menu;
                break;
            }
        }
    }

    // 4. Identify Add Products
    foreach ($menu_items as $m) {
        $clean_menu = strtolower(str_replace(' ', '', $m['n']));
        if (!in_array($clean_menu, $matched_menu_keys)) {
            $add_products[] = ['name' => $m['n'], 'price' => $m['p']];
        }
    }

    $all_ids = array_column($po_products, 'po_product_id');
    return [
        'disable_po_product_ids' => array_values(array_diff($all_ids, $found_ids)),
        'add_products' => $add_products
    ];
}