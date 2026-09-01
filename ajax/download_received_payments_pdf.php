<?php
include '../db.php';
include '../auth.php';
require_admin();

$cutoffDate = $_GET['cutoff_date'] ?? '';

if ($cutoffDate === '') {
    http_response_code(400);
    exit('Cutoff date is required');
}

$stmt = $conn->prepare("
    SELECT received_payments.*
    FROM (
        SELECT
            payment_submissions.id,
            payment_submissions.borrower_id,
            payment_submissions.payment_date,
            payment_submissions.cutoff_date,
            payment_submissions.capital_contribution,
            payment_submissions.loan_payment,
            payment_submissions.reference_number,
            payment_submissions.status,
            borrowers.name,
            users.username,
            'member_submission' AS source_type
        FROM payment_submissions
        JOIN borrowers ON borrowers.id = payment_submissions.borrower_id
        LEFT JOIN users ON users.borrower_id = borrowers.id AND users.status = 'Member'
        WHERE payment_submissions.cutoff_date = ?

        UNION ALL

        SELECT
            capital_contributions.id,
            capital_contributions.borrower_id,
            capital_contributions.contribution_date AS payment_date,
            capital_contributions.contribution_date AS cutoff_date,
            capital_contributions.amount AS capital_contribution,
            0.00 AS loan_payment,
            COALESCE(capital_contributions.reference_number, '') AS reference_number,
            'Approved' AS status,
            borrowers.name,
            users.username,
            'admin_manual' AS source_type
        FROM capital_contributions
        JOIN borrowers ON borrowers.id = capital_contributions.borrower_id
        LEFT JOIN users ON users.borrower_id = borrowers.id AND users.status = 'Member'
        WHERE capital_contributions.contribution_date = ?
        AND capital_contributions.period_label = 'Admin Entry - Verified'
    ) AS received_payments
    ORDER BY received_payments.username ASC, received_payments.name ASC, received_payments.id DESC
");
$stmt->bind_param("ss", $cutoffDate, $cutoffDate);
$stmt->execute();
$payments = $stmt->get_result();

$rows = [];
$totalCapital = 0;
$totalLoan = 0;
$totalAmount = 0;

while ($payment = $payments->fetch_assoc()) {
    $capital = (float)$payment['capital_contribution'];
    $loan = (float)$payment['loan_payment'];
    $total = $capital + $loan;
    $totalCapital += $capital;
    $totalLoan += $loan;
    $totalAmount += $total;

    $rows[] = [
        'borrower' => trim(($payment['username'] ?: $payment['name']) . ' / ' . $payment['name'])
            . (($payment['source_type'] ?? '') === 'admin_manual' ? ' [Admin Manual]' : ''),
        'capital' => $capital,
        'loan' => $loan,
        'total' => $total,
        'reference' => $payment['reference_number'],
        'payment_date' => $payment['payment_date'],
        'status' => $payment['status']
    ];
}

$capconUnpaidStmt = $conn->prepare("
    SELECT borrowers.name, users.username
    FROM borrowers
    LEFT JOIN users ON users.borrower_id = borrowers.id AND users.status = 'Member'
    WHERE borrowers.status = 'Active'
    AND NOT EXISTS (
        SELECT 1
        FROM payment_submissions
        WHERE payment_submissions.borrower_id = borrowers.id
        AND payment_submissions.cutoff_date = ?
        AND payment_submissions.capital_contribution > 0
        AND payment_submissions.status <> 'Rejected'
    )
    AND NOT EXISTS (
        SELECT 1
        FROM capital_contributions
        WHERE capital_contributions.borrower_id = borrowers.id
        AND capital_contributions.contribution_date = ?
        AND capital_contributions.amount > 0
        AND capital_contributions.period_label = 'Admin Entry - Verified'
    )
    ORDER BY users.username ASC, borrowers.name ASC
");
$capconUnpaidStmt->bind_param("ss", $cutoffDate, $cutoffDate);
$capconUnpaidStmt->execute();
$capconUnpaidResult = $capconUnpaidStmt->get_result();
$capconUnpaidRows = [];

while ($member = $capconUnpaidResult->fetch_assoc()) {
    $capconUnpaidRows[] = [
        'member' => trim(($member['username'] ?: $member['name']) . ' / ' . $member['name'])
    ];
}

$loanUnpaidStmt = $conn->prepare("
    SELECT
        borrowers.name,
        users.username,
        loans.id AS loan_id,
        loans.is_guarantor,
        loans.guest_borrower_name,
        IFNULL(SUM(payments.amount),0) AS amount_due
    FROM payments
    JOIN loans ON loans.id = payments.loan_id
    JOIN borrowers ON borrowers.id = loans.borrower_id
    LEFT JOIN users ON users.borrower_id = borrowers.id AND users.status = 'Member'
    WHERE payments.due_date = ?
    AND payments.paid = 0
    AND NOT EXISTS (
        SELECT 1
        FROM payment_submissions
        WHERE payment_submissions.borrower_id = borrowers.id
        AND payment_submissions.cutoff_date = ?
        AND payment_submissions.loan_payment > 0
        AND payment_submissions.status <> 'Rejected'
        AND (
            payment_submissions.selected_loan_id IS NULL
            OR payment_submissions.selected_loan_id = loans.id
        )
    )
    GROUP BY borrowers.id, borrowers.name, users.username, loans.id, loans.is_guarantor, loans.guest_borrower_name
    ORDER BY users.username ASC, borrowers.name ASC, loans.id ASC
");
$loanUnpaidStmt->bind_param("ss", $cutoffDate, $cutoffDate);
$loanUnpaidStmt->execute();
$loanUnpaidResult = $loanUnpaidStmt->get_result();
$loanUnpaidRows = [];

while ($member = $loanUnpaidResult->fetch_assoc()) {
    $loanLabel = 'Loan #' . (int)$member['loan_id'];

    if ((int)($member['is_guarantor'] ?? 0) === 1) {
        $loanLabel .= ' - Co-maker';
        if (!empty($member['guest_borrower_name'])) {
            $loanLabel .= ' for ' . $member['guest_borrower_name'];
        }
    } else {
        $loanLabel .= ' - Personal loan';
    }

    $loanUnpaidRows[] = [
        'member' => trim(($member['username'] ?: $member['name']) . ' / ' . $member['name']),
        'loan' => $loanLabel,
        'amount_due' => (float)$member['amount_due']
    ];
}

function pdf_escape_text($text)
{
    $text = preg_replace('/[^\x20-\x7E]/', '', (string)$text);
    return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
}

function pdf_fit($text, $length)
{
    $text = preg_replace('/\s+/', ' ', trim((string)$text));

    if (strlen($text) > $length) {
        return substr($text, 0, max(0, $length - 3)) . '...';
    }

    return str_pad($text, $length);
}

function pdf_money($amount)
{
    return 'PHP ' . number_format((float)$amount, 2);
}

function pdf_text_line($x, $y, $size, $text, $font = 'F1')
{
    return "BT /{$font} {$size} Tf {$x} {$y} Td (" . pdf_escape_text($text) . ") Tj ET\n";
}

function append_non_payment_section_pages(array &$pages, $cutoffDate, array $capconUnpaidRows, array $loanUnpaidRows, $pageWidth, $pageHeight, $left, $top, $lineHeight)
{
    $sections = [
        [
            'title' => 'MEMBERS WITHOUT CAPCON PAYMENT SUBMISSION',
            'headers' => pdf_fit('Member', 72),
            'rows' => array_map(function ($row) {
                return pdf_fit($row['member'], 72);
            }, $capconUnpaidRows),
            'empty' => 'All active members have capcon payment submission for this cutoff.'
        ],
        [
            'title' => 'MEMBERS WITHOUT LOAN PAYMENT SUBMISSION',
            'headers' => pdf_fit('Member', 38) . pdf_fit('Loan', 38) . pdf_fit('Loan Due', 20),
            'rows' => array_map(function ($row) {
                return pdf_fit($row['member'], 38)
                    . pdf_fit($row['loan'], 38)
                    . pdf_fit(pdf_money($row['amount_due']), 20);
            }, $loanUnpaidRows),
            'empty' => 'No unpaid loan payment submissions for this cutoff.'
        ]
    ];

    $content = '';
    $y = 0;

    foreach ($sections as $section) {
        $sectionRows = $section['rows'] ?: [$section['empty']];
        $rowIndex = 0;

        while ($rowIndex < count($sectionRows)) {
            if ($content === '' || $y < 70) {
                if ($content !== '') {
                    $pages[] = $content;
                }

                $content = '';
                $y = $top;
                $content .= pdf_text_line($left, $y, 13, 'Received Payments Report - Non-Payment List', 'F2');
                $y -= 15;
                $content .= pdf_text_line($left, $y, 9, 'Cutoff Date: ' . date('M d, Y', strtotime($cutoffDate)));
                $y -= 18;
            }

            $content .= pdf_text_line($left, $y, 9, $section['title'], 'F2');
            $y -= 13;
            $content .= pdf_text_line($left, $y, 8, str_repeat('-', 100));
            $y -= $lineHeight;
            $content .= pdf_text_line($left, $y, 8, $section['headers'], 'F2');
            $y -= $lineHeight;
            $content .= pdf_text_line($left, $y, 8, str_repeat('-', 100));
            $y -= $lineHeight;

            while ($rowIndex < count($sectionRows) && $y >= 55) {
                $content .= pdf_text_line($left, $y, 8, $sectionRows[$rowIndex]);
                $y -= $lineHeight;
                $rowIndex++;
            }

            $y -= 12;
        }
    }

    if ($content !== '') {
        $pages[] = $content;
    }
}

function build_received_payments_pdf($cutoffDate, array $rows, $totalCapital, $totalLoan, $totalAmount, array $capconUnpaidRows, array $loanUnpaidRows)
{
    $pageWidth = 842;
    $pageHeight = 595;
    $left = 24;
    $top = 560;
    $lineHeight = 11;
    $maxRowsPerPage = 35;
    $chunks = array_chunk($rows, $maxRowsPerPage);

    if (!$chunks) {
        $chunks = [[]];
    }

    $pages = [];
    $pageNumber = 1;
    $pageCount = count($chunks);

    foreach ($chunks as $chunk) {
        $content = '';
        $y = $top;
        $content .= pdf_text_line($left, $y, 13, 'Received Payments Report', 'F2');
        $content .= pdf_text_line(650, $y, 8, 'Page ' . $pageNumber . ' of ' . $pageCount);
        $y -= 15;
        $content .= pdf_text_line($left, $y, 9, 'Cutoff Date: ' . date('M d, Y', strtotime($cutoffDate)));
        $content .= pdf_text_line(250, $y, 9, 'Generated: ' . date('M d, Y h:i A'));
        $y -= 16;
        $content .= pdf_text_line($left, $y, 8, 'Totals: CapCon ' . pdf_money($totalCapital) . ' | Loan ' . pdf_money($totalLoan) . ' | Overall ' . pdf_money($totalAmount), 'F2');
        $y -= 18;
        $content .= pdf_text_line($left, $y, 8, str_repeat('-', 154));
        $y -= $lineHeight;
        $content .= pdf_text_line($left, $y, 8, pdf_fit('Borrower', 32) . pdf_fit('CapCon', 16) . pdf_fit('Loan', 16) . pdf_fit('Total', 16) . pdf_fit('Reference', 26) . pdf_fit('Pay Date', 14) . pdf_fit('Status', 12), 'F2');
        $y -= $lineHeight;
        $content .= pdf_text_line($left, $y, 8, str_repeat('-', 154));
        $y -= $lineHeight;

        foreach ($chunk as $row) {
            $line = pdf_fit($row['borrower'], 32)
                . pdf_fit(pdf_money($row['capital']), 16)
                . pdf_fit(pdf_money($row['loan']), 16)
                . pdf_fit(pdf_money($row['total']), 16)
                . pdf_fit($row['reference'], 26)
                . pdf_fit(date('m/d/Y', strtotime($row['payment_date'])), 14)
                . pdf_fit($row['status'], 12);
            $content .= pdf_text_line($left, $y, 8, $line);
            $y -= $lineHeight;
        }

        if ($pageNumber === $pageCount) {
            $y -= 6;
            $content .= pdf_text_line($left, $y, 8, str_repeat('-', 154));
            $y -= $lineHeight;
            $content .= pdf_text_line($left, $y, 9, 'TOTAL PAYMENTS MADE DURING THIS CUTOFF', 'F2');
            $y -= 13;
            $content .= pdf_text_line($left, $y, 9, 'Total Capital Contribution: ' . pdf_money($totalCapital));
            $y -= 13;
            $content .= pdf_text_line($left, $y, 9, 'Total Loan Payment: ' . pdf_money($totalLoan));
            $y -= 13;
            $content .= pdf_text_line($left, $y, 10, 'Grand Total Payments: ' . pdf_money($totalAmount), 'F2');
        }

        $pages[] = $content;
        $pageNumber++;
    }

    append_non_payment_section_pages($pages, $cutoffDate, $capconUnpaidRows, $loanUnpaidRows, $pageWidth, $pageHeight, $left, $top, $lineHeight);

    $objects = [];
    $objects[] = '<< /Type /Catalog /Pages 2 0 R >>';

    $pageObjectNumbers = [];
    $nextObject = 4;

    foreach ($pages as $_) {
        $pageObjectNumbers[] = $nextObject;
        $nextObject += 2;
    }

    $kids = implode(' ', array_map(function ($objectNumber) {
        return $objectNumber . ' 0 R';
    }, $pageObjectNumbers));

    $objects[] = "<< /Type /Pages /Kids [{$kids}] /Count " . count($pages) . ' >>';
    $objects[] = '<< /Type /Font /Subtype /Type1 /BaseFont /Courier >>';

    foreach ($pages as $index => $content) {
        $pageObjectNumber = $pageObjectNumbers[$index];
        $contentObjectNumber = $pageObjectNumber + 1;
        $objects[] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 {$pageWidth} {$pageHeight}] /Resources << /Font << /F1 3 0 R /F2 3 0 R >> >> /Contents {$contentObjectNumber} 0 R >>";
        $objects[] = "<< /Length " . strlen($content) . " >>\nstream\n{$content}endstream";
    }

    $pdf = "%PDF-1.4\n";
    $offsets = [0];

    foreach ($objects as $index => $object) {
        $offsets[] = strlen($pdf);
        $objectNumber = $index + 1;
        $pdf .= "{$objectNumber} 0 obj\n{$object}\nendobj\n";
    }

    $xrefOffset = strlen($pdf);
    $pdf .= "xref\n0 " . (count($objects) + 1) . "\n";
    $pdf .= "0000000000 65535 f \n";

    for ($i = 1; $i <= count($objects); $i++) {
        $pdf .= str_pad((string)$offsets[$i], 10, '0', STR_PAD_LEFT) . " 00000 n \n";
    }

    $pdf .= "trailer\n<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\n";
    $pdf .= "startxref\n{$xrefOffset}\n%%EOF";

    return $pdf;
}

$pdf = build_received_payments_pdf($cutoffDate, $rows, $totalCapital, $totalLoan, $totalAmount, $capconUnpaidRows, $loanUnpaidRows);
$fileName = 'received-payments-' . preg_replace('/[^0-9-]/', '', $cutoffDate) . '.pdf';
$disposition = ($_GET['download'] ?? '') === '1' ? 'attachment' : 'inline';

header('Content-Type: application/pdf');
header('Content-Disposition: ' . $disposition . '; filename="' . $fileName . '"');
header('Content-Length: ' . strlen($pdf));
echo $pdf;
exit;
