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
    <div class="image-preview-modal" id="imagePreviewModal" aria-hidden="true">
        <div class="image-preview-backdrop" data-image-preview-close></div>
        <div class="image-preview-dialog" role="dialog" aria-modal="true" aria-label="Reference image preview">
            <div class="image-preview-header">
                <h5 class="mb-0">Reference Image</h5>
                <button type="button" class="btn-close" data-image-preview-close aria-label="Close"></button>
            </div>
            <div class="image-preview-body">
                <img src="" alt="Reference image" id="imagePreviewModalImage">
            </div>
        </div>
    </div>

    <footer class="app-footer text-center text-muted py-3 mt-5">
        <small>All Rights Reserved 2026</small>
    </footer>
    <script>
    document.addEventListener('click', function(event) {
        const previewLink = event.target.closest('[data-image-preview]');
        const closeButton = event.target.closest('[data-image-preview-close]');
        const modal = document.getElementById('imagePreviewModal');
        const image = document.getElementById('imagePreviewModalImage');

        if (previewLink && modal && image) {
            event.preventDefault();
            image.src = previewLink.getAttribute('href');
            modal.classList.add('show');
            modal.setAttribute('aria-hidden', 'false');
            return;
        }

        if (closeButton && modal && image) {
            image.src = '';
            modal.classList.remove('show');
            modal.setAttribute('aria-hidden', 'true');
        }
    });
    </script>
    <?php
}

function render_member_identity($username, $fullName)
{
    $mainName = trim((string)$username) !== '' ? $username : $fullName;
    ?>
    <strong><?= htmlspecialchars($mainName) ?></strong>
    <small class="d-block text-dark-emphasis"><?= htmlspecialchars($fullName) ?></small>
    <?php
}
