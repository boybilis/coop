<?php
include '../db.php';

$start = new DateTimeImmutable("2025-07-15");
$today = new DateTimeImmutable();

$borrowers = $conn->query("SELECT id FROM borrowers");

while($b = $borrowers->fetch_assoc()){

    $borrower_id = (int)$b['id'];

    // =====================================================
    // 1. INITIAL CAPITAL (ONLY ONCE, NEVER OVERWRITE)
    // =====================================================
    $checkInit = $conn->query("
        SELECT id FROM capital_contributions
        WHERE borrower_id = $borrower_id
        AND type = 'INITIAL'
        LIMIT 1
    ");

    if($checkInit->num_rows == 0){
        $conn->query("
            INSERT INTO capital_contributions
            (borrower_id, amount, type, contribution_date, period_label)
            VALUES
            ($borrower_id, 1000, 'INITIAL', '2025-07-15', 'INITIAL CAPITAL')
        ");
    }

    // =====================================================
    // 2. CUT-OFF GENERATION (START AFTER INITIAL)
    // =====================================================
    $scheduleSetting = cooperative_effective_payment_schedule_setting($conn, $start->format('Y-m-d'));
    $cursor = cooperative_next_cutoff_after($start->format('Y-m-d'), $scheduleSetting);

    while($cursor <= $today){

        $date = $cursor->format('Y-m-d');

        // =====================================================
        // CHECK EXISTING (NO DUPLICATE, NO OVERWRITE)
        // =====================================================
        $check = $conn->query("
            SELECT id FROM capital_contributions
            WHERE borrower_id = $borrower_id
            AND contribution_date = '$date'
            AND type = 'CUTOFF'
            LIMIT 1
        ");

        if($check->num_rows == 0){

            $conn->query("
                INSERT INTO capital_contributions
                (borrower_id, amount, type, contribution_date, period_label)
                VALUES
                ($borrower_id, 500, 'CUTOFF', '$date', 'AUTO')
            ");
        }

        $scheduleSetting = cooperative_effective_payment_schedule_setting($conn, $date);
        $cursor = cooperative_next_cutoff_after_cursor($cursor, $scheduleSetting);
    }
}

// =====================================================
// REDIRECT BACK TO CAPITAL MODULE
// =====================================================
header("Location: ../capital.php");
exit;
