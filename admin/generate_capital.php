<?php
include '../db.php';

header("Location: ../capital.php?error=" . urlencode("Generate Capital Cutoffs is disabled. Capital contributions must be recorded through payment approval."));
exit;
