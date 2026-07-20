<?php
include 'db.php';
include 'auth.php';
include 'layout.php';
require_superadmin();

$actionFilter = trim($_GET['action'] ?? '');
$userFilter = trim($_GET['user'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 25;
$offset = ($page - 1) * $perPage;
$where = [];
$types = '';
$params = [];

if ($actionFilter !== '') {
    $where[] = "action LIKE ?";
    $types .= 's';
    $params[] = '%' . $actionFilter . '%';
}

if ($userFilter !== '') {
    $where[] = "(username LIKE ? OR user_status LIKE ?)";
    $types .= 'ss';
    $params[] = '%' . $userFilter . '%';
    $params[] = '%' . $userFilter . '%';
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$countStmt = $conn->prepare("SELECT COUNT(*) AS total FROM audit_trails {$whereSql}");
if ($types !== '') {
    $countStmt->bind_param($types, ...$params);
}
$countStmt->execute();
$totalRows = (int)$countStmt->get_result()->fetch_assoc()['total'];
$totalPages = max(1, (int)ceil($totalRows / $perPage));

$listTypes = $types . 'ii';
$listParams = array_merge($params, [$perPage, $offset]);
$stmt = $conn->prepare("
    SELECT *
    FROM audit_trails
    {$whereSql}
    ORDER BY created_at DESC, id DESC
    LIMIT ? OFFSET ?
");
$stmt->bind_param($listTypes, ...$listParams);
$stmt->execute();
$logs = $stmt->get_result();
?>
<!DOCTYPE html>
<html>
<head>
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Audit Trails</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="assets/css/mobile.css">
<link rel="stylesheet" href="assets/css/theme.css?v=20260720-ui">
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
            <form class="row g-2">
                <div class="col-md-4">
                    <input type="text" name="action" class="form-control" placeholder="Filter by action" value="<?= htmlspecialchars($actionFilter) ?>">
                </div>
                <div class="col-md-4">
                    <input type="text" name="user" class="form-control" placeholder="Filter by user or role" value="<?= htmlspecialchars($userFilter) ?>">
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
                <tbody>
                    <?php if($logs->num_rows === 0): ?>
                        <tr><td colspan="7" class="text-center text-muted">No audit records found.</td></tr>
                    <?php endif; ?>
                    <?php while($log = $logs->fetch_assoc()): ?>
                        <tr>
                            <td><?= htmlspecialchars($log['created_at']) ?></td>
                            <td><?= htmlspecialchars($log['username'] ?? 'System') ?></td>
                            <td><?= htmlspecialchars($log['user_status'] ?? '') ?></td>
                            <td><span class="badge bg-primary"><?= htmlspecialchars($log['action']) ?></span></td>
                            <td>
                                <?= htmlspecialchars($log['description']) ?>
                                <?php if(!empty($log['metadata'])): ?>
                                    <details class="mt-1">
                                        <summary class="text-muted small">Metadata</summary>
                                        <pre class="small bg-light border rounded p-2 mb-0"><?= htmlspecialchars($log['metadata']) ?></pre>
                                    </details>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars(trim(($log['entity_type'] ?? '') . ' #' . ($log['entity_id'] ?? ''), ' #')) ?></td>
                            <td><?= htmlspecialchars($log['ip_address'] ?? '') ?></td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>

            <div class="d-flex justify-content-between align-items-center">
                <small class="text-muted">Page <?= $page ?> of <?= $totalPages ?> · <?= $totalRows ?> records</small>
                <div>
                    <?php
                    $queryBase = http_build_query(array_filter([
                        'action' => $actionFilter,
                        'user' => $userFilter
                    ], fn($value) => $value !== ''));
                    $prefix = $queryBase ? $queryBase . '&' : '';
                    ?>
                    <a class="btn btn-outline-secondary btn-sm <?= $page <= 1 ? 'disabled' : '' ?>" href="audit_trails.php?<?= $prefix ?>page=<?= $page - 1 ?>">Previous</a>
                    <a class="btn btn-outline-secondary btn-sm <?= $page >= $totalPages ? 'disabled' : '' ?>" href="audit_trails.php?<?= $prefix ?>page=<?= $page + 1 ?>">Next</a>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<?php render_footer(); ?>
</body>
</html>
