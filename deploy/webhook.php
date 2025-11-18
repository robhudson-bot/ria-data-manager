<?php
/**
 * GitHub Webhook Receiver for RIA Data Manager
 * 
 * @version 1.0.3
 */

require_once __DIR__ . '/config.php';

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

$response = ['success' => false, 'message' => '', 'timestamp' => date('c')];

function log_message($message, $level = 'INFO') {
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents(DEPLOY_LOG_FILE, "[{$timestamp}] [{$level}] {$message}\n", FILE_APPEND);
}

function send_notification($subject, $message, $is_error = false) {
    if (!DEPLOY_ENABLE_NOTIFICATIONS) return;
    
    $status = $is_error ? 'ERROR' : 'SUCCESS';
    $color = $is_error ? '#dc3545' : '#28a745';
    
    $html = "<div style='font-family:Arial'><div style='background:{$color};color:white;padding:15px'><h2 style='margin:0'>[{$status}] {$subject}</h2></div><div style='border:1px solid #ddd;padding:20px'><p><strong>Site:</strong> the-ria.ca</p><p><strong>Time:</strong> " . date('Y-m-d H:i:s') . "</p><hr><div style='background:#f5f5f5;padding:15px;font-family:monospace'>{$message}</div></div></div>";
    
    wp_mail(DEPLOY_NOTIFICATION_EMAIL, $subject, $html, ['From: RIA Deployment <noreply@the-ria.ca>', 'Content-Type: text/html; charset=UTF-8']);
}

function verify_signature($payload, $signature) {
    if (empty($signature)) return false;
    list($algo, $hash) = explode('=', $signature, 2);
    if ($algo !== 'sha256') return false;
    return hash_equals(hash_hmac('sha256', $payload, DEPLOY_WEBHOOK_SECRET), $hash);
}

try {
    log_message('Webhook received from ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
    
    $raw_payload = file_get_contents('php://input');
    if (empty($raw_payload)) {
        throw new Exception('Empty payload');
    }
    
    // Handle form-encoded or raw JSON
    $content_type = $_SERVER['CONTENT_TYPE'] ?? '';
    if (strpos($content_type, 'application/x-www-form-urlencoded') !== false) {
        parse_str($raw_payload, $parsed);
        $payload = $parsed['payload'] ?? '';
        if (empty($payload)) throw new Exception('No payload in form data');
    } else {
        $payload = $raw_payload;
    }
    
    // Verify signature
    if (!verify_signature($raw_payload, $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '')) {
        log_message('Invalid signature', 'ERROR');
        throw new Exception('Invalid signature');
    }
    
    // Decode JSON with error handling
    $data = json_decode($payload, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        log_message('JSON Error: ' . json_last_error_msg(), 'ERROR');
        log_message('Payload preview: ' . substr($payload, 0, 200), 'DEBUG');
        throw new Exception('Invalid JSON: ' . json_last_error_msg());
    }
    
    // Check event type
    $event_type = $_SERVER['HTTP_X_GITHUB_EVENT'] ?? '';
    if ($event_type !== 'push') {
        $response['message'] = "Event '{$event_type}' ignored";
        echo json_encode($response);
        exit;
    }
    
    // Check branch
    $branch = str_replace('refs/heads/', '', $data['ref'] ?? '');
    if ($branch !== DEPLOY_BRANCH) {
        $response['message'] = "Branch '{$branch}' ignored";
        echo json_encode($response);
        exit;
    }
    
    // Get commit info
    $commit_message = $data['head_commit']['message'] ?? 'No message';
    $commit_author = $data['head_commit']['author']['name'] ?? 'Unknown';
    $commit_sha = substr($data['head_commit']['id'] ?? '', 0, 7);
    
    log_message("Push to {$branch} by {$commit_author}: {$commit_message}");
    
    // Check skip flag
    if (preg_match('/\[(skip|no)[\s-]?deploy\]/i', $commit_message)) {
        $response['message'] = 'Deployment skipped';
        echo json_encode($response);
        exit;
    }
    
    // Deploy
    log_message("Starting deployment...");
    require_once __DIR__ . '/deployer.php';
    $deployer = new RIA_Deployer();
    $result = $deployer->deploy();
    
    if ($result['success']) {
        log_message("Deployment SUCCESS", 'SUCCESS');
        send_notification("Deployment Successful", "Commit: {$commit_sha}<br>Author: {$commit_author}<br>Message: {$commit_message}");
        
        $response['success'] = true;
        $response['message'] = 'Deployment completed';
        $response['commit'] = $commit_sha;
    } else {
        throw new Exception($result['message'] ?? 'Deployment failed');
    }
    
} catch (Exception $e) {
    log_message('ERROR: ' . $e->getMessage(), 'ERROR');
    send_notification("Deployment Failed", $e->getMessage(), true);
    $response['message'] = 'Failed: ' . $e->getMessage();
    http_response_code(500);
}

echo json_encode($response, JSON_PRETTY_PRINT);
log_message('Completed');
