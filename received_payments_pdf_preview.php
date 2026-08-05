<?php
include 'db.php';
include 'auth.php';
include 'layout.php';
require_admin();

$cutoffDate = $_GET['cutoff_date'] ?? '';

if ($cutoffDate === '') {
    header('Location: received_payments.php?error=' . urlencode('Cutoff date is required'));
    exit;
}

$previewUrl = 'ajax/download_received_payments_pdf.php?cutoff_date=' . urlencode($cutoffDate);
$downloadUrl = $previewUrl . '&download=1';
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Received Payments PDF Preview</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="assets/css/mobile.css">
<link rel="stylesheet" href="assets/css/theme.css?v=20260722-placeholders">
<style>
html,
body {
    height: 100%;
}
.pdf-preview-frame {
    width: 100%;
    height: calc(100vh - 150px);
    border: 1px solid #dee2e6;
    border-radius: .5rem;
    background: #fff;
}
</style>
</head>
<body class="bg-light">
<div class="container-fluid py-3">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h4 class="mb-0">Received Payments PDF Preview</h4>
            <small class="text-muted">Cut-off Date: <?= date('M d, Y', strtotime($cutoffDate)) ?></small>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= htmlspecialchars($downloadUrl) ?>" class="btn btn-danger">
                Download PDF
            </a>
            <button type="button" class="btn btn-outline-secondary" onclick="window.close()">
                Close
            </button>
        </div>
    </div>

    <iframe src="<?= htmlspecialchars($previewUrl) ?>" class="pdf-preview-frame" title="Received Payments PDF Preview"></iframe>
</div>
</body>
</html>
