<?php
include 'db.php';
include 'auth.php';
include 'layout.php';
include 'totp.php';

if (is_logged_in()) {
    if (isset($conn)) {
        refresh_logged_in_user($conn);
    }

    if (is_admin_user() && (int)($_SESSION['two_factor_enabled'] ?? 0) !== 1) {
        header("Location: admin_settings.php?force_2fa=1#twoFactorSetupCard");
    } else {
        header("Location: " . (is_admin_user() ? 'index.php' : 'member_dashboard.php'));
    }
    exit;
}

$error = '';
$showPasswordSetup = false;
$showTwoFactorSetup = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['two_factor_login'])) {
        $pendingUserId = (int)($_SESSION['pending_2fa_user_id'] ?? 0);
        $pendingUsername = $_SESSION['pending_2fa_username'] ?? '';
        $pendingStatus = $_SESSION['pending_2fa_status'] ?? '';
        $pendingBorrowerId = $_SESSION['pending_2fa_borrower_id'] ?? null;
        $code = $_POST['two_factor_code'] ?? '';

        $stmt = $conn->prepare("
            SELECT id, username, status, borrower_id, two_factor_secret, two_factor_enabled
            FROM users
            WHERE id = ?
            AND status IN ('Admin', 'SuperAdmin')
            LIMIT 1
        ");
        $stmt->bind_param("i", $pendingUserId);
        $stmt->execute();
        $pendingUser = $stmt->get_result()->fetch_assoc();

        if (
            $pendingUser
            && $pendingUser['username'] === $pendingUsername
            && $pendingUser['status'] === $pendingStatus
            && (int)$pendingUser['two_factor_enabled'] === 1
            && !empty($pendingUser['two_factor_secret'])
            && totp_verify($pendingUser['two_factor_secret'], $code)
        ) {
            unset(
                $_SESSION['pending_2fa_user_id'],
                $_SESSION['pending_2fa_username'],
                $_SESSION['pending_2fa_status'],
                $_SESSION['pending_2fa_borrower_id']
            );

            $_SESSION['user_id'] = $pendingUser['id'];
            $_SESSION['username'] = $pendingUser['username'];
            $_SESSION['user_status'] = $pendingUser['status'];
            $_SESSION['borrower_id'] = $pendingUser['borrower_id'];
            $_SESSION['two_factor_enabled'] = (int)$pendingUser['two_factor_enabled'];
            $_SESSION['active_member_user_id'] = $pendingUser['id'];
            $_SESSION['active_borrower_id'] = $pendingUser['borrower_id'];

            audit_log($conn, 'login', 'Admin logged in successfully with authenticator 2FA.', 'users', (int)$pendingUser['id'], [
                'username' => $pendingUser['username'],
                'status' => $pendingUser['status'],
                'two_factor' => true
            ]);

            header("Location: " . ((int)($pendingUser['two_factor_enabled'] ?? 0) === 1 ? 'index.php' : 'admin_settings.php?force_2fa=1#twoFactorSetupCard'));
            exit;
        }

        audit_log($conn, 'failed_admin_2fa_login', 'Admin entered an invalid authenticator login code.', 'users', $pendingUserId, [
            'username' => $pendingUsername,
            'status' => $pendingStatus
        ]);

        $error = "Invalid authenticator code";
        $showTwoFactorSetup = true;
    } else {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $conn->prepare("SELECT id, username, password, status, borrower_id, two_factor_secret, two_factor_enabled FROM users WHERE username = ? LIMIT 1");
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
        if (in_array($user['status'], ['Admin', 'SuperAdmin'], true) && (int)($user['two_factor_enabled'] ?? 0) === 1) {
            $_SESSION['pending_2fa_user_id'] = $user['id'];
            $_SESSION['pending_2fa_username'] = $user['username'];
            $_SESSION['pending_2fa_status'] = $user['status'];
            $_SESSION['pending_2fa_borrower_id'] = $user['borrower_id'];
            $showTwoFactorSetup = true;
        } else {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['user_status'] = $user['status'];
        $_SESSION['borrower_id'] = $user['borrower_id'];
        $_SESSION['two_factor_enabled'] = (int)($user['two_factor_enabled'] ?? 0);
        $_SESSION['active_member_user_id'] = $user['id'];
        $_SESSION['active_borrower_id'] = $user['borrower_id'];

        audit_log($conn, 'login', 'User logged in successfully.', 'users', (int)$user['id'], [
            'username' => $user['username'],
            'status' => $user['status']
        ]);

        if (in_array($user['status'], ['Admin', 'SuperAdmin'], true)) {
            header("Location: admin_settings.php?force_2fa=1#twoFactorSetupCard");
        } else {
            header("Location: member_dashboard.php");
        }
        exit;
        }
    } elseif (!$showPasswordSetup) {
        $error = "Invalid username or password";
    }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Login</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="assets/css/mobile.css">
<link rel="stylesheet" href="assets/css/theme.css?v=20260722-placeholders">
<style>
.two-factor-modal {
    border: 0;
    border-radius: 22px;
    overflow: hidden;
    background: linear-gradient(145deg, #0b1220, #102a43);
    color: #ffffff;
    box-shadow: 0 24px 70px rgba(15, 23, 42, .45);
}

.two-factor-modal .modal-header,
.two-factor-modal .modal-footer {
    border: 0;
}

.two-factor-icon {
    width: 58px;
    height: 58px;
    border-radius: 18px;
    display: grid;
    place-items: center;
    margin: 0 auto 14px;
    background: rgba(255, 255, 255, .14);
    border: 1px solid rgba(255, 255, 255, .24);
    font-weight: 800;
    font-size: 1.35rem;
    color: #67e8f9;
}

.two-factor-code-grid {
    display: grid;
    grid-template-columns: repeat(6, minmax(38px, 1fr));
    gap: 10px;
}

.two-factor-digit {
    height: 58px;
    border-radius: 14px;
    border: 1px solid rgba(103, 232, 249, .35);
    background: rgba(255, 255, 255, .95);
    color: #0f172a;
    text-align: center;
    font-size: 1.5rem;
    font-weight: 800;
    box-shadow: inset 0 0 0 1px rgba(15, 23, 42, .04);
}

.two-factor-digit:focus {
    outline: none;
    border-color: #67e8f9;
    box-shadow: 0 0 0 4px rgba(103, 232, 249, .25);
}

.two-factor-help {
    color: rgba(255, 255, 255, .72);
}

.two-factor-mobile-helper {
    display: none;
    padding: 12px;
    border-radius: 14px;
    background: rgba(103, 232, 249, .12);
    border: 1px solid rgba(103, 232, 249, .28);
}

@media (max-width: 420px) {
    .two-factor-code-grid {
        gap: 6px;
    }

    .two-factor-digit {
        height: 50px;
        border-radius: 12px;
        font-size: 1.25rem;
    }

    .two-factor-mobile-helper {
        display: block;
    }
}
</style>
</head>
<body class="login-page bg-light">
<?php render_navbar(); ?>
<div class="container">
    <div class="row justify-content-center mt-5">
        <div class="col-md-4">
            <div class="card login-card glass-card glass-midnight">
                <div class="card-body">
                    <div class="login-icon mb-3">IB</div>
                    <h4 class="mb-1">Cooperative System Login</h4>
                    <p class="login-subtitle mb-4">Loan and Savings Management System</p>

                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>

                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label">Username</label>
                            <input type="text" name="username" class="form-control" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Password</label>
                            <input type="password" name="password" class="form-control" required>
                        </div>

                        <button class="btn login-btn w-100">Login</button>
                    </form>

                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="twoFactorLoginModal" data-bs-backdrop="static" data-bs-keyboard="false">
  <div class="modal-dialog">
    <div class="modal-content two-factor-modal">
      <form method="POST" id="twoFactorLoginForm">
        <div class="modal-header justify-content-center pt-4 position-relative">
          <button type="button" class="btn-close btn-close-white position-absolute top-0 end-0 m-3" data-bs-dismiss="modal" aria-label="Close"></button>
          <div class="text-center">
            <div class="two-factor-icon">2FA</div>
            <h5 class="mb-0">Authenticator Code</h5>
          </div>
        </div>
        <div class="modal-body px-4">
          <p class="two-factor-help text-center mb-4">
              Enter the 6-digit code from Microsoft Authenticator or Google Authenticator.
          </p>
          <div class="two-factor-mobile-helper mb-3">
            <div class="small fw-bold text-info mb-1">Using a phone?</div>
            <div class="small two-factor-help mb-2">
              Open your Authenticator app, copy the 6-digit code, then return here. Pasting the code will fill all boxes automatically.
            </div>
            <button type="button" class="btn btn-outline-info btn-sm w-100" id="authenticatorMobileHelpBtn">
              Open My Authenticator App
            </button>
          </div>
          <input type="hidden" name="two_factor_login" value="1">
          <input type="hidden" name="two_factor_code" id="twoFactorCode" required>
          <div class="two-factor-code-grid" id="twoFactorCodeGrid">
            <input type="text" class="two-factor-digit" inputmode="numeric" pattern="[0-9]" maxlength="1" autocomplete="one-time-code" aria-label="Digit 1">
            <input type="text" class="two-factor-digit" inputmode="numeric" pattern="[0-9]" maxlength="1" aria-label="Digit 2">
            <input type="text" class="two-factor-digit" inputmode="numeric" pattern="[0-9]" maxlength="1" aria-label="Digit 3">
            <input type="text" class="two-factor-digit" inputmode="numeric" pattern="[0-9]" maxlength="1" aria-label="Digit 4">
            <input type="text" class="two-factor-digit" inputmode="numeric" pattern="[0-9]" maxlength="1" aria-label="Digit 5">
            <input type="text" class="two-factor-digit" inputmode="numeric" pattern="[0-9]" maxlength="1" aria-label="Digit 6">
          </div>
          <div class="small text-warning text-center mt-3 d-none" id="twoFactorCodeError">
            Enter the complete 6-digit authenticator code.
          </div>
        </div>
        <div class="modal-footer px-4 pb-4">
          <button class="btn btn-info fw-bold w-100 text-dark">Verify Login</button>
        </div>
      </form>
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
<?php if ($showTwoFactorSetup): ?>
const twoFactorModalElement = document.getElementById('twoFactorLoginModal');
new bootstrap.Modal(twoFactorModalElement).show();
twoFactorModalElement.addEventListener('shown.bs.modal', function () {
    const firstDigit = document.querySelector('.two-factor-digit');
    if (firstDigit) {
        firstDigit.focus();
    }
});
<?php endif; ?>

document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('twoFactorLoginForm');
    const hiddenCode = document.getElementById('twoFactorCode');
    const errorBox = document.getElementById('twoFactorCodeError');
    const mobileHelpButton = document.getElementById('authenticatorMobileHelpBtn');
    const digits = Array.from(document.querySelectorAll('.two-factor-digit'));

    if (!form || !hiddenCode || digits.length !== 6) {
        return;
    }

    function syncCode() {
        hiddenCode.value = digits.map(input => input.value).join('');
        if (/^\d{6}$/.test(hiddenCode.value)) {
            errorBox.classList.add('d-none');
        }
    }

    function fillDigits(value) {
        const cleanValue = value.replace(/\D/g, '').slice(0, 6);
        digits.forEach((input, index) => {
            input.value = cleanValue[index] || '';
        });
        syncCode();
        const nextIndex = Math.min(cleanValue.length, digits.length - 1);
        digits[nextIndex].focus();
    }

    digits.forEach((input, index) => {
        input.addEventListener('input', function () {
            const value = input.value.replace(/\D/g, '');

            if (value.length > 1) {
                fillDigits(value);
                return;
            }

            input.value = value;
            syncCode();

            if (value && index < digits.length - 1) {
                digits[index + 1].focus();
            }
        });

        input.addEventListener('keydown', function (event) {
            if (event.key === 'Backspace' && !input.value && index > 0) {
                digits[index - 1].focus();
                digits[index - 1].value = '';
                syncCode();
            }
        });

        input.addEventListener('paste', function (event) {
            event.preventDefault();
            fillDigits((event.clipboardData || window.clipboardData).getData('text'));
        });
    });

    form.addEventListener('submit', function (event) {
        syncCode();

        if (!/^\d{6}$/.test(hiddenCode.value)) {
            event.preventDefault();
            errorBox.classList.remove('d-none');
            digits.find(input => !input.value)?.focus();
        }
    });

    if (mobileHelpButton) {
        mobileHelpButton.addEventListener('click', function () {
            if (window.appShowToast) {
                window.appShowToast('Open your Authenticator app, then return here and paste or type the 6-digit code.', 'info');
            } else {
                alert('Open your Authenticator app, then return here and paste or type the 6-digit code.');
            }

            setTimeout(function () {
                digits[0].focus();
            }, 300);
        });
    }
});

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
<?php render_footer(); ?>
</body>
</html>

