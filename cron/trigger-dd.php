<?php

define('SkipAuth', true);
require_once('../_config.php');
$success = false;
$response = '';
$brand_id = getVarNum('brand_id', null);

if (empty($brand_id) && $brand_id !== '0') {
	$error = "Error: You must select a Brand!  " . $brand_id;
	echo json_encode([
		'success' => false,
		'response' => $error
	]);
	exit;
}

$response .= 'Brand_ID is: ' . $brand_id . '<br>------------------------------------';

// Attempt to find PHP executable (common Plesk paths)
$possiblePhpPaths = [
    '/usr/bin/php',
    '/opt/plesk/php/8.2/bin/php',
    '/opt/plesk/php/8.1/bin/php',
    '/opt/plesk/php/8.0/bin/php',
    '/opt/plesk/php/7.4/bin/php'
];

// Find the first valid PHP executable
$phpPath = null;
foreach ($possiblePhpPaths as $path) {
    if (file_exists($path) && is_executable($path)) {
        $phpPath = $path;
        break;
    }
}
$response2 .= '<br>path: ' . $path;
// Fallback to 'which php' if no path is found
if (!$phpPath) {
    $phpPath = trim(shell_exec('which php'));
}

// Validate PHP path
if (!$phpPath || !file_exists($phpPath)) {
   $error = "Error: PHP executable not found. Please ensure PHP CLI is installed.\n";
	echo json_encode([
		'success' => false,
		'response' => $error
	]);
	exit;
}

// Path to dd.php (relative to current script)
$ddScript = __DIR__ . '/push_dd.php';

// Verify dd.php exists
if (!file_exists($ddScript)) {
	$error = "</br>Error: push_dd.php not found in " . __DIR__ . "\n";
	echo json_encode([
		'success' => false,
		'response' => $error
	]);
	exit;
}
$store_id = null;
//$store_id = 1;

$rs = getRs("SELECT store_id, store_name, db, api_url, auth_code, partner_key FROM store WHERE store_id <> 2 AND " . is_enabled() . iif($store_id, " AND store_id = {$store_id}") . " ORDER BY store_id");

$pids = [];

foreach ($rs as $r) {
    // Define log file path with timestamp
    $logFile = "../log/dd_script_{$r['store_id']}_" . date('Ymd') . ".log";

    // Run in background with nohup, redirecting all streams
    $command = "nohup " 
		. escapeshellcmd($phpPath) . ' ' 
		. escapeshellarg($ddScript) . ' ' 
		. escapeshellarg($r['store_id']) . ' '
    	. escapeshellarg($brand_id)
		. ' < /dev/null >> ' . escapeshellarg($logFile) 
		. ' 2>&1 & echo $!';

    // Execute command and capture output (PID)
    $output = [];
    exec($command, $output, $returnVar);

    // Log execution status
    $timestamp = date('Y-m-d H:i:s');
    if ($returnVar === 0 && !empty($output)) {
        // Store the PID from the output
        $pid = (int) $output[0];
		$pids[$r['store_id']] = [
			'pid' => $pid,
			'store_name' => $r['store_name']
		];
        $successMsg = "[$timestamp] Command triggered for store_id {$r['store_id']} with PID $pid\n";
        $response .= "</br>[$timestamp] {$r['store_name']}: Sync started (PID: $pid)\n";
        file_put_contents($logFile, $successMsg, FILE_APPEND);
    } else {
        $errorMsg = "[$timestamp] Error triggering push_dd.php for store_id {$r['store_id']}. Return code: $returnVar. Output: " . implode("\n", $output) . "\n";
        $response .= "</br>" . $errorMsg;
        file_put_contents($logFile, $errorMsg, FILE_APPEND);
    }

    // Optional: Remove or reduce sleep to launch all scripts faster
    // sleep(60); // Comment out or reduce if you want to start all scripts quickly
}

// Wait for all background processes to complete
$timestamp = date('Y-m-d H:i:s');
$response .= "<br>------------------------------------<br> 
[$timestamp] Waiting for all background processes to complete...\n
<br>------------------------------------";

foreach ($pids as $store_id => $data) {
    $pid = $data['pid'];
    $store_name = $data['store_name'];
    $timestamp = date('Y-m-d H:i:s');
    $response2 .= "</br> [$timestamp] Checking status of PID $pid for store_id $store_id...\n";

    // Poll until the process is no longer running
    while (posix_getpgid($pid) !== false) {
        $timestamp = date('Y-m-d H:i:s');
        $response2 .= "</br> [$timestamp] PID $pid (store_id $store_id) is still running...\n";
        sleep(10); // Check every 5 seconds to avoid excessive CPU usage
    }

    // Log completion
    $timestamp = date('Y-m-d H:i:s');
    $logFile = "/var/log/dd_script_{$store_id}_" . date('Ymd') . ".log";
    $completionMsg = "[$timestamp] {$store_name}: Sync completed successfully (PID $pid)\n";
    $response .= "</br>" . $completionMsg;
    file_put_contents($logFile, $completionMsg, FILE_APPEND);
}

$timestamp = date('Y-m-d H:i:s');
$response .= "<br>------------------------------------<br>[$timestamp] Re-sync has completed successfully.  Please check the <a href='https://mis.theartisttree.com/daily-discounts-logs'>Logs page</a> to ensure completion.";
$success = true;
echo json_encode(array('success' => $success, 'response' => $response));

?>