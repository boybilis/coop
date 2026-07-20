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
    <div class="app-toast-container" id="appToastContainer" aria-live="polite" aria-atomic="true"></div>

    <div class="app-confirm-modal" id="appConfirmModal" aria-hidden="true" style="display:none;">
        <div class="app-confirm-backdrop"></div>
        <div class="app-confirm-dialog" role="dialog" aria-modal="true" aria-label="Confirmation">
            <h5 id="appConfirmTitle">Confirm Action</h5>
            <p id="appConfirmMessage">Are you sure?</p>
            <div class="app-confirm-actions">
                <button type="button" class="btn btn-outline-secondary" id="appConfirmCancel">Cancel</button>
                <button type="button" class="btn btn-primary" id="appConfirmOk">Continue</button>
            </div>
        </div>
    </div>

    <div class="image-preview-modal" id="imagePreviewModal" aria-hidden="true" style="display:none;">
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
    window.appToasts = window.appToasts || [];

    window.appShowToast = function(message, type = 'info') {
        const container = document.getElementById('appToastContainer');

        if (!container || !message) {
            return;
        }

        const toast = document.createElement('div');
        toast.className = 'app-toast app-toast-' + type;
        toast.innerHTML = '<span>' + message + '</span><button type="button" aria-label="Close">&times;</button>';

        toast.querySelector('button').addEventListener('click', function() {
            toast.remove();
        });

        container.appendChild(toast);

        setTimeout(function() {
            toast.classList.add('show');
        }, 10);

        setTimeout(function() {
            toast.classList.remove('show');
            setTimeout(function() {
                toast.remove();
            }, 250);
        }, 4500);
    };

    window.appConfirm = function(message, options = {}) {
        return new Promise(function(resolve) {
            const modal = document.getElementById('appConfirmModal');
            const title = document.getElementById('appConfirmTitle');
            const messageBox = document.getElementById('appConfirmMessage');
            const cancelButton = document.getElementById('appConfirmCancel');
            const okButton = document.getElementById('appConfirmOk');

            if (!modal || !title || !messageBox || !cancelButton || !okButton) {
                resolve(false);
                return;
            }

            title.textContent = options.title || 'Confirm Action';
            messageBox.textContent = message || 'Are you sure?';
            okButton.textContent = options.okText || 'Continue';
            cancelButton.textContent = options.cancelText || 'Cancel';
            okButton.className = 'btn ' + (options.okClass || 'btn-primary');

            const close = function(result) {
                modal.classList.remove('show');
                modal.style.display = 'none';
                modal.setAttribute('aria-hidden', 'true');
                okButton.onclick = null;
                cancelButton.onclick = null;
                resolve(result);
            };

            okButton.onclick = function() {
                close(true);
            };

            cancelButton.onclick = function() {
                close(false);
            };

            modal.querySelector('.app-confirm-backdrop').onclick = function() {
                close(false);
            };

            modal.classList.add('show');
            modal.style.display = 'flex';
            modal.setAttribute('aria-hidden', 'false');
        });
    };

    document.addEventListener('DOMContentLoaded', function() {
        const sessionToastMessage = sessionStorage.getItem('appToastMessage');
        const sessionToastType = sessionStorage.getItem('appToastType') || 'info';

        if (sessionToastMessage) {
            window.appShowToast(sessionToastMessage, sessionToastType);
            sessionStorage.removeItem('appToastMessage');
            sessionStorage.removeItem('appToastType');
        }

        (window.appToasts || []).forEach(function(toast) {
            window.appShowToast(toast.message, toast.type || 'info');
        });
        window.appToasts = [];
    });

    document.addEventListener('submit', function(event) {
        const form = event.target.closest('form[data-confirm]');

        if (!form || form.dataset.confirmed === '1') {
            return;
        }

        event.preventDefault();

        window.appConfirm(form.dataset.confirm, {
            okText: form.dataset.confirmOk || 'Continue',
            okClass: form.dataset.confirmClass || 'btn-primary'
        }).then(function(confirmed) {
            if (confirmed) {
                form.dataset.confirmed = '1';
                form.submit();
            }
        });
    });

    document.addEventListener('click', function(event) {
        const button = event.target.closest('[data-click-confirm]');

        if (!button || button.dataset.confirmed === '1') {
            return;
        }

        event.preventDefault();

        window.appConfirm(button.dataset.clickConfirm, {
            okText: button.dataset.confirmOk || 'Continue',
            okClass: button.dataset.confirmClass || 'btn-primary'
        }).then(function(confirmed) {
            if (confirmed) {
                button.dataset.confirmed = '1';
                button.click();
            }
        });
    });

    document.addEventListener('click', function(event) {
        const previewLink = event.target.closest('[data-image-preview]');
        const closeButton = event.target.closest('[data-image-preview-close]');
        const modal = document.getElementById('imagePreviewModal');
        const image = document.getElementById('imagePreviewModalImage');

        if (previewLink && modal && image) {
            event.preventDefault();
            image.src = previewLink.getAttribute('href');
            modal.classList.add('show');
            modal.style.display = 'flex';
            modal.setAttribute('aria-hidden', 'false');
            return;
        }

        if (closeButton && modal && image) {
            image.src = '';
            modal.classList.remove('show');
            modal.style.display = 'none';
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

