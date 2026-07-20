<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth.php';
require_superadmin();

header('Content-Type: application/json');

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
$html = '';

if ($logs->num_rows === 0) {
    $html = '<tr><td colspan="7" class="text-center text-muted">No audit records found.</td></tr>';
}

while ($log = $logs->fetch_assoc()) {
    $entity = trim(($log['entity_type'] ?? '') . ' #' . ($log['entity_id'] ?? ''), ' #');
    $metadata = '';

    if (!empty($log['metadata'])) {
        $metadata = '<details class="mt-1">'
            . '<summary class="text-muted small">Metadata</summary>'
            . '<pre class="small bg-light border rounded p-2 mb-0">' . htmlspecialchars($log['metadata']) . '</pre>'
            . '</details>';
    }

    $html .= '<tr>';
    $html .= '<td>' . htmlspecialchars($log['created_at']) . '</td>';
    $html .= '<td>' . htmlspecialchars($log['username'] ?? 'System') . '</td>';
    $html .= '<td>' . htmlspecialchars($log['user_status'] ?? '') . '</td>';
    $html .= '<td><span class="badge bg-primary">' . htmlspecialchars($log['action']) . '</span></td>';
    $html .= '<td>' . htmlspecialchars($log['description']) . $metadata . '</td>';
    $html .= '<td>' . htmlspecialchars($entity) . '</td>';
    $html .= '<td>' . htmlspecialchars($log['ip_address'] ?? '') . '</td>';
    $html .= '</tr>';
}

echo json_encode([
    'html' => $html,
    'page' => $page,
    'total_pages' => $totalPages,
    'total_rows' => $totalRows
]);
