<?php
/**
 * Public Certificate Verification & Lookup API
 * CertificateHub
 */
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json');

$email = trim($_POST['email'] ?? $_GET['email'] ?? $_POST['query'] ?? $_GET['query'] ?? '');

if (empty($email)) {
    echo json_encode(['success' => false, 'message' => 'Please provide your registered email address.']);
    exit;
}

try {
    // 1. Locate participant in active or any registered cohort
    $participant = dbFetchOne("
        SELECT 
            p.id as participant_id, p.cohort_id, p.full_name, p.email, p.status as participant_status,
            ch.name as cohort_name, ch.batch_code, ch.instructor_name, ch.instructor_title, ch.issue_date, ch.location
        FROM participants p
        JOIN cohorts ch ON ch.id = p.cohort_id
        WHERE LOWER(TRIM(p.email)) = LOWER(TRIM(?))
        ORDER BY p.id DESC
        LIMIT 1
    ", [$email]);

    if ($participant) {
        if ($participant['participant_status'] === 'revoked') {
            echo json_encode([
                'success' => true,
                'found'   => false,
                'email'   => $email,
                'message' => 'This credential has been revoked by the issuing institution.',
                'support_url' => 'certificate_not_found.php?email=' . urlencode($email)
            ]);
            exit;
        }

        // 2. Fetch existing valid certificate
        $cert = dbFetchOne("
            SELECT id as cert_id, certificate_token, document_hash, issued_at, download_count, status as cert_status
            FROM certificates 
            WHERE participant_id = ? AND status = 'valid'
            ORDER BY id DESC LIMIT 1
        ", [$participant['participant_id']]);

        // 3. Auto-mint certificate if participant is enrolled but certificate record wasn't generated yet
        if (!$cert) {
            $cohortId = (int)$participant['cohort_id'];
            $template = dbFetchOne("SELECT id FROM certificate_templates WHERE cohort_id = ? AND is_active = 1 LIMIT 1", [$cohortId]);
            if (!$template) {
                $template = dbFetchOne("SELECT id FROM certificate_templates WHERE cohort_id = ? LIMIT 1", [$cohortId]);
            }
            $templateId = (int)($template['id'] ?? 1);

            $randomToken = generateNextCertificateToken($cohortId);
            $docHash = hash('sha256', $randomToken . '|' . $participant['full_name'] . '|' . $participant['email'] . '|' . time());

            dbQuery("
                INSERT INTO certificates (participant_id, template_id, certificate_token, document_hash, status, issued_at)
                VALUES (?, ?, ?, ?, 'valid', NOW())
            ", [$participant['participant_id'], $templateId, $randomToken, $docHash]);

            dbQuery("UPDATE participants SET status = 'issued' WHERE id = ?", [$participant['participant_id']]);

            $cert = [
                'cert_id' => (int)dbLastInsertId(),
                'certificate_token' => $randomToken,
                'document_hash' => $docHash,
                'issued_at' => date('Y-m-d H:i:s'),
                'download_count' => 0,
                'cert_status' => 'valid'
            ];
        }

        $issueDate = !empty($cert['issued_at']) ? date('F j, Y', strtotime($cert['issued_at'])) : date('F j, Y', strtotime($participant['issue_date']));
        $hashSnippet = substr($cert['document_hash'], 0, 16) . '...';

        echo json_encode([
            'success' => true,
            'found'   => true,
            'data'    => [
                'recipient_name'    => $participant['full_name'],
                'email'             => $participant['email'],
                'cohort_name'       => $participant['cohort_name'],
                'batch_code'        => $participant['batch_code'],
                'instructor_name'   => $participant['instructor_name'],
                'issue_date'        => $issueDate,
                'certificate_token' => $cert['certificate_token'],
                'document_hash'     => $cert['document_hash'],
                'hash_snippet'      => $hashSnippet,
                'view_url'          => 'certificate_found_result.php?email=' . urlencode($participant['email'])
            ]
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'found'   => false,
            'email'   => $email,
            'message' => 'No active certificate record was found for this email address.',
            'support_url' => 'certificate_not_found.php?email=' . urlencode($email)
        ]);
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error during lookup: ' . $e->getMessage()
    ]);
}
