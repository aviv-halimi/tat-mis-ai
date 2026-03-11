<?php
/**
 * Get AI prompt(s) for the settings UI.
 * GET: key (optional) — if provided, return that prompt only; else return all.
 */
require_once dirname(__FILE__) . '/../_config.php';
header('Cache-Control: no-cache');
header('Content-type: application/json');

$key = isset($_GET['key']) ? trim((string) $_GET['key']) : '';
$db  = $_Session->db;

if ($key !== '') {
    $row = getRow(getRs(
        "SELECT prompt_key, prompt_label, content, date_updated FROM {$db}.ai_prompts WHERE prompt_key = ? LIMIT 1",
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
    $rs = getRs("SELECT prompt_key, prompt_label, content, date_updated FROM {$db}.ai_prompts ORDER BY prompt_key", []);
    if ($rs) {
        while ($r = getRow($rs)) {
            $list[] = [
                'prompt_key'   => $r['prompt_key'],
                'prompt_label' => $r['prompt_label'],
                'content'       => $r['content'],
                'date_updated'  => $r['date_updated'],
            ];
        }
    }
    echo json_encode(['success' => true, 'prompts' => $list]);
}
