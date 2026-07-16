<?php
include '../db.php';

$start = new DateTime("2025-07-15");
$today = new DateTime();

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

    $cursor = clone $start;

    // 🔥 IMPORTANT: move immediately to FIRST VALID CUT-OFF
    $cursor->modify('last day of this month');

    while($cursor <= $today){

        $cutoffs = [];

        // Cutoff 1: end of current month
        $cutoffs[] = clone $cursor;

        // Cutoff 2: 15th of next month
        $next = clone $cursor;
        $next->modify('first day of next month');
        $next->setDate(
            $next->format('Y'),
            $next->format('m'),
            15
        );

        if($next <= $today){
            $cutoffs[] = $next;
        }

        foreach($cutoffs as $dateObj){

            $date = $dateObj->format('Y-m-d');

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
        }

        // move to next month cycle
        $cursor->modify('first day of next month');
        $cursor->setDate(
            $cursor->format('Y'),
            $cursor->format('m'),
            15
        );
    }
}

// =====================================================
// REDIRECT BACK TO CAPITAL MODULE
// =====================================================
header("Location: ../capital.php");
exit;