<?php
/**
 * CertificateHub - Entry Router
 */

if (isset($_GET['admin'])) {
    header('Location: admin/organizer_dashboard.php');
    exit;
}

$query = !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '';
header('Location: public/public_certificate_download.php' . $query);
exit;