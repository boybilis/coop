<?php
include 'db.php';
include 'auth.php';
include 'layout.php';
include 'totp.php';
require_admin();

$adminUsers = null;
$currentAdminTwoFactor = null;
$twoFactorSetupUri = null;

$currentAdminStmt = $conn->prepare("
    SELECT two_factor_secret, two_factor_enabled, two_factor_confirmed_at
    FROM users
    WHERE id = ?
    LIMIT 1
");
$currentAdminStmt->bind_param("i", $_SESSION['user_id']);
$currentAdminStmt->execute();
$currentAdminTwoFactor = $currentAdminStmt->get_result()->fetch_assoc();

if ($currentAdminTwoFactor && !empty($currentAdminTwoFactor['two_factor_secret']) && !(int)$currentAdminTwoFactor['two_factor_enabled']) {
    $twoFactorSetupUri = totp_otpauth_uri('Cooperative System', $_SESSION['username'] ?? 'admin', $currentAdminTwoFactor['two_factor_secret']);
}

if (is_superadmin_user()) {
    $adminUsers = $conn->query("
        SELECT id, username, status, two_factor_enabled, two_factor_confirmed_at, created_at
        FROM users
        WHERE status IN ('Admin', 'SuperAdmin')
        ORDER BY FIELD(status, 'SuperAdmin', 'Admin'), username ASC
    ");
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Admin Settings</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="assets/css/mobile.css">
<link rel="stylesheet" href="assets/css/theme.css?v=20260722-placeholders">
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
</head>
<body class="bg-light">
<?php render_navbar(); ?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="mb-0">Admin Settings</h3>
        <a href="index.php" class="btn btn-outline-secondary">Dashboard</a>
    </div>

    <?php if(isset($_GET['password_updated'])): ?>
        <script>window.appToasts = window.appToasts || []; window.appToasts.push({type:'success', message:'Admin password updated.'});</script>
    <?php endif; ?>
    <?php if(isset($_GET['username_updated'])): ?>
        <script>window.appToasts = window.appToasts || []; window.appToasts.push({type:'success', message:'SuperAdmin username updated.'});</script>
    <?php endif; ?>
    <?php if(isset($_GET['admin_created'])): ?>
        <script>window.appToasts = window.appToasts || []; window.appToasts.push({type:'success', message:'New admin user created.'});</script>
    <?php endif; ?>
    <?php if(isset($_GET['admin_deleted'])): ?>
        <script>window.appToasts = window.appToasts || []; window.appToasts.push({type:'warning', message:'Admin user deleted.'});</script>
    <?php endif; ?>
    <?php if(isset($_GET['two_factor_setup'])): ?>
        <script>window.appToasts = window.appToasts || []; window.appToasts.push({type:'info', message:'Authenticator setup started. Add the setup key to your app.'});</script>
    <?php endif; ?>
    <?php if(isset($_GET['two_factor_enabled'])): ?>
        <script>window.appToasts = window.appToasts || []; window.appToasts.push({type:'success', message:'Authenticator 2FA enabled.'});</script>
    <?php endif; ?>
    <?php if(isset($_GET['two_factor_disabled'])): ?>
        <script>window.appToasts = window.appToasts || []; window.appToasts.push({type:'warning', message:'Authenticator 2FA disabled.'});</script>
    <?php endif; ?>
    <?php if(isset($_GET['force_2fa'])): ?>
        <script>window.appToasts = window.appToasts || []; window.appToasts.push({type:'warning', message:'Authenticator setup is required before accessing admin pages.'});</script>
        <div class="alert alert-warning">
            Admin accounts must set up Microsoft Authenticator or Google Authenticator before accessing the admin dashboard.
        </div>
    <?php endif; ?>
    <?php if(isset($_GET['error'])): ?>
        <script>window.appToasts = window.appToasts || []; window.appToasts.push({type:'error', message:<?= json_encode($_GET['error']) ?>});</script>
    <?php endif; ?>

    <div class="row">
        <div class="col-lg-5 mb-3">
            <div class="card shadow">
                <div class="card-header">
                    <h5 class="mb-0">Change My Password</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="ajax/change_admin_password.php">
                        <div class="mb-3">
                            <label class="form-label">Current Password</label>
                            <input type="password" name="current_password" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">New Password</label>
                            <input type="password" name="new_password" class="form-control" minlength="6" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Confirm New Password</label>
                            <input type="password" name="confirm_password" class="form-control" minlength="6" required>
                        </div>
                        <button class="btn btn-primary">Update Password</button>
                    </form>
                </div>
            </div>

            <div class="card shadow mt-3" id="twoFactorSetupCard">
                <div class="card-header">
                    <h5 class="mb-0">Authenticator App 2FA</h5>
                </div>
                <div class="card-body">
                    <?php if((int)($currentAdminTwoFactor['two_factor_enabled'] ?? 0) === 1): ?>
                        <p class="text-success mb-2">
                            Authenticator 2FA is enabled.
                        </p>
                        <small class="text-muted d-block mb-3">
                            Enabled since <?= htmlspecialchars($currentAdminTwoFactor['two_factor_confirmed_at'] ?? '') ?>.
                        </small>
                        <form method="POST" action="ajax/disable_admin_2fa.php" data-confirm="Disable authenticator 2FA for your admin login?" data-confirm-ok="Disable 2FA" data-confirm-class="btn-danger">
                            <div class="mb-3">
                                <label class="form-label">Current Password</label>
                                <input type="password" name="current_password" class="form-control" required>
                            </div>
                            <button class="btn btn-outline-danger">Disable 2FA</button>
                        </form>
                    <?php elseif($twoFactorSetupUri): ?>
                        <p class="text-muted">
                            Add this setup key to Microsoft Authenticator or Google Authenticator, then enter the generated 6-digit code.
                        </p>
                        <label class="form-label">Setup Key</label>
                        <div class="input-group mb-2">
                            <input type="text" class="form-control" value="<?= htmlspecialchars($currentAdminTwoFactor['two_factor_secret']) ?>" readonly>
                            <button class="btn btn-outline-secondary" type="button" onclick="navigator.clipboard && navigator.clipboard.writeText('<?= htmlspecialchars($currentAdminTwoFactor['two_factor_secret'], ENT_QUOTES, 'UTF-8') ?>')">Copy</button>
                        </div>
                        <small class="text-muted d-block mb-3">
                            In the authenticator app, choose manual setup or enter setup key. Account name: <?= htmlspecialchars($_SESSION['username'] ?? 'admin') ?>.
                        </small>
                        <a href="<?= htmlspecialchars($twoFactorSetupUri) ?>" class="btn btn-outline-primary btn-sm mb-3">
                            Open Authenticator Setup Link
                        </a>
                        <form method="POST" action="ajax/confirm_admin_2fa.php">
                            <div class="mb-3">
                                <label class="form-label">6-Digit Code</label>
                                <input type="text" name="two_factor_code" class="form-control" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required>
                            </div>
                            <button class="btn btn-success">Enable 2FA</button>
                        </form>
                    <?php else: ?>
                        <p class="text-muted">
                            Protect admin login with a 6-digit code from Microsoft Authenticator or Google Authenticator.
                        </p>
                        <form method="POST" action="ajax/start_admin_2fa_setup.php">
                            <button class="btn btn-primary">Start Authenticator Setup</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php if(is_superadmin_user()): ?>
            <div class="col-lg-7 mb-3">
                <div class="card shadow mb-3">
                    <div class="card-header">
                        <h5 class="mb-0">Change SuperAdmin Username</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="ajax/change_superadmin_username.php" class="row g-3">
                            <div class="col-md-8">
                                <label class="form-label">New Username</label>
                                <input type="text" name="username" class="form-control" value="<?= htmlspecialchars($_SESSION['username'] ?? '') ?>" required>
                            </div>
                            <div class="col-md-4 d-flex align-items-end">
                                <button class="btn btn-warning w-100">Update Username</button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="card shadow mb-3">
                    <div class="card-header">
                        <h5 class="mb-0">Create Admin User</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="ajax/create_admin_user.php" class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Username</label>
                                <input type="text" name="username" class="form-control" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Password</label>
                                <input type="password" name="password" class="form-control" minlength="6" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Confirm Password</label>
                                <input type="password" name="confirm_password" class="form-control" minlength="6" required>
                            </div>
                            <div class="col-12">
                                <button class="btn btn-success">Create Admin</button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="card shadow">
                    <div class="card-header">
                        <h5 class="mb-0">Admin Users</h5>
                    </div>
                    <div class="card-body table-responsive">
                        <table class="table table-bordered table-hover align-middle" id="adminUsersTable">
                            <thead class="table-dark">
                                <tr>
                                    <th>Username</th>
                                    <th>Role</th>
                                    <th>2FA</th>
                                    <th>Created</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while($admin = $adminUsers->fetch_assoc()): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($admin['username']) ?></td>
                                        <td><span class="badge bg-<?= $admin['status'] === 'SuperAdmin' ? 'danger' : 'primary' ?>"><?= htmlspecialchars($admin['status']) ?></span></td>
                                        <td>
                                            <?php if((int)$admin['two_factor_enabled'] === 1): ?>
                                                <span class="badge bg-success">Enabled</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Off</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= htmlspecialchars($admin['created_at']) ?></td>
                                        <td>
                                            <?php if($admin['status'] === 'SuperAdmin'): ?>
                                                <span class="text-muted">Protected</span>
                                            <?php else: ?>
                                                <form method="POST" action="ajax/delete_admin_user.php" class="m-0" data-confirm="Delete this admin user?" data-confirm-ok="Delete" data-confirm-class="btn-danger">
                                                    <input type="hidden" name="admin_id" value="<?= (int)$admin['id'] ?>">
                                                    <button class="btn btn-outline-danger btn-sm">Delete</button>
                                                </form>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script>
$(document).ready(function(){
    $('#adminUsersTable').DataTable({
        pageLength: 10,
        order: [[1, 'desc'], [0, 'asc']],
        columnDefs: [
            { orderable: false, targets: 4 }
        ]
    });
});
</script>
<?php render_footer(); ?>
</body>
</html>
