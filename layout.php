<?php
function render_navbar($title = 'Cooperative Loan and Savings Management System')
{
    ?>
    <nav class="navbar navbar-expand-lg navbar-dark app-navbar sticky-top shadow-sm">
        <div class="container">
            <a class="navbar-brand fw-bold text-white" href="<?= current_user_status() === 'Admin' ? 'index.php' : 'member_dashboard.php' ?>">
                <?= htmlspecialchars($title) ?>
            </a>
            <?php if (is_logged_in()): ?>
                <div class="ms-auto">
                    <a href="logout.php" class="btn btn-outline-light btn-sm">Logout</a>
                </div>
            <?php endif; ?>
        </div>
    </nav>
    <?php
}

function render_footer()
{
    ?>
    <footer class="app-footer text-center text-muted py-3 mt-5">
        <small>All Rights Reserved 2026</small>
    </footer>
    <?php
}
