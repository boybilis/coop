<?php
include 'db.php';
include 'auth.php';

if (is_logged_in()) {
    header("Location: " . (current_user_status() === 'Admin' ? 'index.php' : 'member_dashboard.php'));
    exit;
}

$error = '';
$showPasswordSetup = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $conn->prepare("SELECT id, username, password, status, borrower_id FROM users WHERE username = ? LIMIT 1");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    if (!$user) {
        $memberStmt = $conn->prepare("SELECT id, name FROM borrowers WHERE LOWER(name) = LOWER(?) LIMIT 1");
        $memberStmt->bind_param("s", $username);
        $memberStmt->execute();
        $member = $memberStmt->get_result()->fetch_assoc();

        if ($member) {
            $createStmt = $conn->prepare("
                INSERT INTO users (username, password, status, borrower_id)
                VALUES (?, '', 'Member', ?)
            ");
            $createStmt->bind_param("si", $member['name'], $member['id']);
            $createStmt->execute();

            $user = [
                "id" => $createStmt->insert_id,
                "username" => $member['name'],
                "password" => "",
                "status" => "Member",
                "borrower_id" => $member['id']
            ];
        }
    }

    if ($user && $user['status'] === 'Member' && $user['password'] === '' && $password === 'member') {
        $_SESSION['pending_member_user_id'] = $user['id'];
        $_SESSION['pending_member_username'] = $user['username'];
        $showPasswordSetup = true;
    } elseif ($user && $user['password'] !== '' && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['user_status'] = $user['status'];
        $_SESSION['borrower_id'] = $user['borrower_id'];
        $_SESSION['active_member_user_id'] = $user['id'];
        $_SESSION['active_borrower_id'] = $user['borrower_id'];

        if ($user['status'] === 'Admin') {
            header("Location: index.php");
        } else {
            header("Location: member_dashboard.php");
        }
        exit;
    } elseif (!$showPasswordSetup) {
        $error = "Invalid username or password";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
<title>Login</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container">
    <div class="row justify-content-center mt-5">
        <div class="col-md-4">
            <div class="card shadow">
                <div class="card-body">
                    <h4 class="mb-3">Cooperative System Login</h4>

                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>

                    <form method="POST">
                        <div class="mb-3">
                            <label>Username</label>
                            <input type="text" name="username" class="form-control" required>
                        </div>

                        <div class="mb-3">
                            <label>Password</label>
                            <input type="password" name="password" class="form-control" required>
                        </div>

                        <button class="btn btn-primary w-100">Login</button>
                    </form>

                    <small class="text-muted d-block mt-3">
                        Admin: admin / admin123<br>
                        Members: member name / member
                    </small>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="passwordSetupModal" data-bs-backdrop="static" data-bs-keyboard="false">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5>Set Member Password</h5>
      </div>
      <div class="modal-body">
        <p class="text-muted">
            Enter your birthday as your new password using MMDDYYYY format.
        </p>
        <input type="password" id="birthdayPassword" class="form-control" placeholder="MMDDYYYY" maxlength="8">
        <div class="text-danger small mt-2 d-none" id="passwordSetupError"></div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-primary" onclick="setMemberPassword()">Continue</button>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
<?php if ($showPasswordSetup): ?>
new bootstrap.Modal(document.getElementById('passwordSetupModal')).show();
<?php endif; ?>

function setMemberPassword(){
    let password = document.getElementById('birthdayPassword').value.trim();
    let errorBox = document.getElementById('passwordSetupError');

    errorBox.classList.add('d-none');
    errorBox.innerText = '';

    if(!/^\d{8}$/.test(password)){
        errorBox.innerText = 'Use MMDDYYYY format.';
        errorBox.classList.remove('d-none');
        return;
    }

    fetch('ajax/set_member_password.php', {
        method: 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded'},
        body: 'password=' + encodeURIComponent(password)
    })
    .then(res => res.json())
    .then(data => {
        if(data.error){
            errorBox.innerText = data.error;
            errorBox.classList.remove('d-none');
            return;
        }

        window.location = 'member_dashboard.php';
    });
}
</script>
</body>
</html>
