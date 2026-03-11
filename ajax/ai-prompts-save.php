<?php
/**
 * Save one AI prompt. Creates row if missing (prompt_key must exist in ai_prompts).
 * POST: prompt_key (required), content (required, can be empty string).
 */
require_once dirname(__FILE__) . '/../_config.php';
header('Cache-Control: no-cache');
header('Content-type: application/json');

$key    = isset($_POST['prompt_key']) ? trim((string) $_POST['prompt_key']) : '';
$content = isset($_POST['content']) ? (string) $_POST['content'] : '';

if ($key === '') {
    echo json_encode(['success' => false, 'error' => 'Missing prompt_key']);
    exit;
}

// ai_prompts lives in the main app DB (theartisttree), not the session store DB
$ai_db = (defined('dbhost') && preg_match('/dbname=([^;]+)/', dbhost, $m)) ? trim($m[1]) : 'theartisttree';

$exists = getRow(getRs("SELECT id FROM `{$ai_db}`.ai_prompts WHERE prompt_key = ? LIMIT 1", [$key]));
if (!$exists) {
    echo json_encode(['success' => false, 'error' => 'Unknown prompt_key. Add the key to the ai_prompts table first.']);
    exit;
}

setRs("UPDATE `{$ai_db}`.ai_prompts SET content = ?, date_updated = NOW() WHERE prompt_key = ?", [$content, $key]);

echo json_encode([
    'success' => true,
    'prompt_key' => $key,
    'message' => 'Saved.',
]);
