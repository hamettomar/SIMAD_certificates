<?php
/**
 * Asynchronous Download Tracking Endpoint
 * CertificateHub
 */
require_once __DIR__ . '/db.php';

header('Content-Type: application/json');

$certId = (int)($_POST['cert_id'] ?? $_GET['cert_id'] ?? 0);
$token  = trim($_POST['token'] ?? $_GET['token'] ?? '');

try {
    if ($certId > 0) {
        dbQuery("UPDATE certificates SET download_count = download_count + 1, last_downloaded_at = NOW() WHERE id = ?", [$certId]);
        echo json_encode(['success' => true, 'cert_id' => $certId]);
        exit;
    } elseif (!empty($token)) {
        dbQuery("UPDATE certificates SET download_count = download_count + 1, last_downloaded_at = NOW() WHERE certificate_token = ?", [$token]);
        echo json_encode(['success' => true, 'token' => $token]);
        exit;
    }
    echo json_encode(['success' => false, 'error' => 'Missing cert_id or token']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
