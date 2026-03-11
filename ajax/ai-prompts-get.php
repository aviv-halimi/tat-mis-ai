<?php
/**
 * Get AI prompt(s) for the settings UI.
 * GET: key (optional) — if provided, return that prompt only; else return all.
 */
require_once dirname(__FILE__) . '/../_config.php';
header('Cache-Control: no-cache');
header('Content-type: application/json');

$key = isset($_GET['key']) ? trim((string) $_GET['key']) : '';
// ai_prompts lives in the main app DB (theartisttree), not the session store DB
$ai_db = (defined('dbhost') && preg_match('/dbname=([^;]+)/', dbhost, $m)) ? trim($m[1]) : 'theartisttree';

$rs = @getRs("SELECT prompt_key, prompt_label, content, date_updated FROM `{$ai_db}`.ai_prompts ORDER BY prompt_key", []);
if ($rs === false) {
    echo json_encode(['success' => false, 'error' => 'Table ai_prompts not found or not accessible. Run doc/ai_prompts-table.sql.']);
    exit;
}

if ($key !== '') {
    $row = getRow(getRs(
        "SELECT prompt_key, prompt_label, content, date_updated FROM `{$ai_db}`.ai_prompts WHERE prompt_key = ? LIMIT 1",
        [$key]
    ));
    if (!$row) {
        echo json_encode(['success' => false, 'error' => 'Prompt not found']);
        exit;
    }
    echo json_encode([
        'success' => true,
        'prompt_key'   => $row['prompt_key'],
        'prompt_label' => $row['prompt_label'],
        'content'      => $row['content'],
        'date_updated' => $row['date_updated'],
    ]);
} else {
    $list = [];
    if (is_array($rs)) {
        foreach ($rs as $r) {
            $list[] = [
                'prompt_key'   => $r['prompt_key'],
                'prompt_label' => $r['prompt_label'],
                'content'      => $r['content'],
                'date_updated' => $r['date_updated'],
            ];
        }
    }
    echo json_encode(['success' => true, 'prompts' => $list]);
}
