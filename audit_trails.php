<?php
include 'db.php';
include 'auth.php';
include 'layout.php';
require_superadmin();

$actionFilter = trim($_GET['action'] ?? '');
$userFilter = trim($_GET['user'] ?? '');
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Audit Trails</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="assets/css/mobile.css">
<link rel="stylesheet" href="assets/css/theme.css?v=20260722-placeholders">
</head>
<body class="bg-light">
<?php render_navbar(); ?>

<div class="container-fluid mt-4 px-3 px-md-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="mb-0">Audit Trails</h3>
        <a href="index.php" class="btn btn-outline-secondary">Dashboard</a>
    </div>

    <div class="card shadow mb-3">
        <div class="card-body">
            <form class="row g-2" id="auditFilterForm">
                <div class="col-md-4">
                    <input type="text" name="action" id="auditActionFilter" class="form-control" placeholder="Filter by action" value="<?= htmlspecialchars($actionFilter) ?>">
                </div>
                <div class="col-md-4">
                    <input type="text" name="user" id="auditUserFilter" class="form-control" placeholder="Filter by user or role" value="<?= htmlspecialchars($userFilter) ?>">
                </div>
                <div class="col-md-4">
                    <button class="btn btn-primary">Search</button>
                    <a href="audit_trails.php" class="btn btn-outline-secondary">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow">
        <div class="card-body table-responsive">
            <table class="table table-bordered table-hover align-middle">
                <thead class="table-dark">
                    <tr>
                        <th>Date/Time</th>
                        <th>User</th>
                        <th>Role</th>
                        <th>Action</th>
                        <th>Description</th>
                        <th>Entity</th>
                        <th>IP</th>
                    </tr>
                </thead>
                <tbody id="auditTrailsTableBody">
                    <tr><td colspan="7" class="text-center text-muted">Loading audit trails...</td></tr>
                </tbody>
            </table>

            <div class="d-flex justify-content-between align-items-center">
                <small class="text-muted" id="auditTrailsSummary">Loading...</small>
                <div>
                    <button class="btn btn-outline-secondary btn-sm" id="auditPrevPage" disabled>Previous</button>
                    <button class="btn btn-outline-secondary btn-sm" id="auditNextPage" disabled>Next</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
let auditCurrentPage = 1;
let auditTotalPages = 1;

function auditFilters(){
    return {
        action: document.getElementById('auditActionFilter').value.trim(),
        user: document.getElementById('auditUserFilter').value.trim()
    };
}

function loadAuditTrails(page = 1){
    const body = document.getElementById('auditTrailsTableBody');
    const summary = document.getElementById('auditTrailsSummary');
    const prev = document.getElementById('auditPrevPage');
    const next = document.getElementById('auditNextPage');
    const filters = auditFilters();
    const params = new URLSearchParams({
        page: page,
        action: filters.action,
        user: filters.user
    });

    body.innerHTML = '<tr><td colspan="7" class="text-center text-muted">Loading audit trails...</td></tr>';
    prev.disabled = true;
    next.disabled = true;

    fetch('ajax/audit_trails_table.php?' + params.toString(), {
        headers: {'X-Requested-With': 'XMLHttpRequest'}
    })
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                throw new Error(data.error);
            }

            auditCurrentPage = data.page;
            auditTotalPages = data.total_pages;
            body.innerHTML = data.html;
            summary.innerText = 'Page ' + data.page + ' of ' + data.total_pages + ' · ' + data.total_rows + ' records';
            prev.disabled = data.page <= 1;
            next.disabled = data.page >= data.total_pages;
        })
        .catch(() => {
            body.innerHTML = '<tr><td colspan="7" class="text-center text-danger">Unable to load audit trails.</td></tr>';
            summary.innerText = 'Unable to load data.';
        });
}

document.getElementById('auditFilterForm').addEventListener('submit', function(event){
    event.preventDefault();
    loadAuditTrails(1);
});

document.getElementById('auditPrevPage').addEventListener('click', function(){
    if (auditCurrentPage > 1) {
        loadAuditTrails(auditCurrentPage - 1);
    }
});

document.getElementById('auditNextPage').addEventListener('click', function(){
    if (auditCurrentPage < auditTotalPages) {
        loadAuditTrails(auditCurrentPage + 1);
    }
});

loadAuditTrails(1);
</script>
<?php render_footer(); ?>
</body>
</html>
