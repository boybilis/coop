<?php
include 'db.php';
include 'auth.php';
include 'layout.php';
require_admin();

$totalInterestQuery = $conn->query("SELECT SUM(interest) AS total_interest FROM loans");
$totalInterest = $totalInterestQuery->fetch_assoc()['total_interest'] ?? 0;

$borrowerCountQuery = $conn->query("SELECT COUNT(*) AS total FROM borrowers");
$borrowerCount = $borrowerCountQuery->fetch_assoc()['total'] ?? 0;

$sharePerBorrower = $borrowerCount > 0 ? $totalInterest / $borrowerCount : 0;

$members = $conn->query("
    SELECT 
        borrowers.*,
        COUNT(loans.id) AS total_loans,
        COALESCE(SUM(loans.amount),0) AS total_borrowed,
        COALESCE(savings_summary.total_savings,0) AS total_savings,
        COALESCE(capital_summary.total_capital,0) AS total_capital
    FROM borrowers
    LEFT JOIN loans ON loans.borrower_id = borrowers.id
    LEFT JOIN (
        SELECT
            borrower_id,
            IFNULL(SUM(CASE WHEN type = 'DEPOSIT' THEN amount ELSE -amount END),0) AS total_savings
        FROM savings_transactions
        GROUP BY borrower_id
    ) AS savings_summary ON savings_summary.borrower_id = borrowers.id
    LEFT JOIN (
        SELECT borrower_id, IFNULL(SUM(amount),0) AS total_capital
        FROM capital_contributions
        GROUP BY borrower_id
    ) AS capital_summary ON capital_summary.borrower_id = borrowers.id
    GROUP BY borrowers.id
    ORDER BY borrowers.id DESC
");
?>
<!DOCTYPE html>
<html>
<head>
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Member Management</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="assets/css/mobile.css">
<link rel="stylesheet" href="assets/css/theme.css">
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
</head>

<body class="bg-light">
<?php render_navbar(); ?>
<div class="container mt-4">

<div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0">Member Management</h3>
    <div>
        <a href="index.php" class="btn btn-outline-secondary">Dashboard</a>
    </div>
</div>

<div class="card shadow">
    <div class="card-header d-flex justify-content-between">
        <h5>Members</h5>
        <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#addBorrowerModal">
            + Add Member
        </button>
    </div>

    <div class="card-body">
        <div class="table-responsive">
<table class="table table-bordered table-hover align-middle" id="borrowerTable">
            <thead class="table-dark">
                <tr>
                    <th>Name</th>
                    <th>Date Created</th>
                    <th>Total Loans</th>
                    <th>Total Borrowed</th>
                    <th>Total Savings</th>
                    <th>Total Capcon</th>
                    <th>Interest Share</th>
                    <th>Status</th>
                    <th width="180">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php while($row = $members->fetch_assoc()): ?>
                <tr data-member-id="<?= $row['id'] ?>">
                    <td class="member-name"><strong><?= htmlspecialchars($row['name']) ?></strong></td>
                    <td><?= $row['created_at'] ?></td>
                    <td><span class="badge bg-primary"><?= $row['total_loans'] ?></span></td>
                    <td>₱<?= number_format($row['total_borrowed'],2) ?></td>
                    <td class="text-info fw-bold">₱<?= number_format($row['total_savings'],2) ?></td>
                    <td class="text-warning fw-bold">₱<?= number_format($row['total_capital'],2) ?></td>
                    <td><span class="text-success fw-bold">₱<?= number_format($sharePerBorrower,2) ?></span></td>
                    <td class="member-status">
                        <span class="badge bg-<?= $row['status'] === 'Active' ? 'success' : 'secondary' ?>">
                            <?= $row['status'] ?>
                        </span>
                    </td>
                    <td>
                        <button
                            class="btn btn-warning btn-sm"
                            onclick="openEditMember(<?= $row['id'] ?>, '<?= htmlspecialchars($row['name'], ENT_QUOTES) ?>', '<?= $row['status'] ?>')">
                            Edit
                        </button>

                        <?php if($row['status'] === 'Active'): ?>
                            <button class="btn btn-outline-danger btn-sm" onclick="setInactive(<?= $row['id'] ?>)">
                                Set Inactive
                            </button>
                        <?php else: ?>
                            <span class="text-muted small">Inactive</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
</div>
    </div>
</div>

</div>

<div class="modal fade" id="editMemberModal">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5>Edit Member</h5>
        <button class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="editMemberId">

        <div class="mb-3">
            <label>Member Name</label>
            <input type="text" id="editMemberName" class="form-control" required>
        </div>

        <div class="mb-3">
            <label>Status</label>
            <select id="editMemberStatus" class="form-control">
                <option value="Active">Active</option>
                <option value="Inactive">Inactive</option>
            </select>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-primary" onclick="saveMemberEdit()">Save Changes</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="addBorrowerModal">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5>Add Member</h5>
        <button class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="text" id="borrowerName" class="form-control" placeholder="Enter member name">
      </div>
      <div class="modal-footer">
        <button class="btn btn-success" onclick="saveBorrower()">Save</button>
      </div>
    </div>
  </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script>
let borrowerDataTable;

$(document).ready(function(){
    borrowerDataTable = $('#borrowerTable').DataTable({
        pageLength: 10,
        order: [[1, 'desc']]
    });
});

function saveBorrower(){
    let name = document.getElementById("borrowerName").value.trim();

    if(!name){
        alert("Enter member name");
        return;
    }

    fetch('ajax/save_borrower.php', {
        method: 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded'},
        body: 'name=' + encodeURIComponent(name)
    })
    .then(res => res.json())
    .then(data => {
        if(data.error){
            alert(data.error);
            return;
        }

        let row = `
            <tr data-member-id="${data.id}">
                <td class="member-name"><strong>${data.name}</strong></td>
                <td>Just now</td>
                <td><span class="badge bg-primary">0</span></td>
                <td>₱0.00</td>
                <td class="text-info fw-bold">₱0.00</td>
                <td class="text-warning fw-bold">₱0.00</td>
                <td><span class="text-success fw-bold">₱0.00</span></td>
                <td class="member-status"><span class="badge bg-success">Active</span></td>
                <td>
                    <button class="btn btn-warning btn-sm" onclick="openEditMember(${data.id}, '${data.name.replace(/'/g, "\\'")}', 'Active')">
                        Edit
                    </button>
                    <button class="btn btn-outline-danger btn-sm" onclick="setInactive(${data.id})">
                        Set Inactive
                    </button>
                </td>
            </tr>
        `;

        borrowerDataTable.row.add($(row)).draw(false);
        document.getElementById("borrowerName").value = '';

        let modal = bootstrap.Modal.getInstance(document.getElementById('addBorrowerModal'));
        modal.hide();
    });
}

function openEditMember(id, name, status){
    document.getElementById('editMemberId').value = id;
    document.getElementById('editMemberName').value = name;
    document.getElementById('editMemberStatus').value = status;

    new bootstrap.Modal(document.getElementById('editMemberModal')).show();
}

function saveMemberEdit(){
    let id = document.getElementById('editMemberId').value;
    let name = document.getElementById('editMemberName').value.trim();
    let status = document.getElementById('editMemberStatus').value;

    if(!name){
        alert('Enter member name');
        return;
    }

    fetch('ajax/update_member.php', {
        method: 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded'},
        body: 'id=' + encodeURIComponent(id)
            + '&name=' + encodeURIComponent(name)
            + '&status=' + encodeURIComponent(status)
    })
    .then(res => res.json())
    .then(data => {
        if(data.error){
            alert(data.error);
            return;
        }

        updateMemberRow(data.id, data.name, data.status);

        let modal = bootstrap.Modal.getInstance(document.getElementById('editMemberModal'));
        modal.hide();
    });
}

function setInactive(id){
    if(!confirm('Set this member as inactive?')){
        return;
    }

    fetch('ajax/set_member_inactive.php', {
        method: 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded'},
        body: 'id=' + encodeURIComponent(id)
    })
    .then(res => res.json())
    .then(data => {
        if(data.error){
            alert(data.error);
            return;
        }

        let row = getMemberRow(data.id);
        if(!row){
            return;
        }
        let name = row.querySelector('.member-name').innerText.trim();
        updateMemberRow(data.id, name, data.status);
    });
}

function updateMemberRow(id, name, status){
    let row = getMemberRow(id);
    if(!row){
        return;
    }

    let statusClass = status === 'Active' ? 'success' : 'secondary';
    let actionHtml = `
        <button class="btn btn-warning btn-sm" onclick="openEditMember(${id}, '${escapeJsString(name)}', '${status}')">
            Edit
        </button>
    `;

    if(status === 'Active'){
        actionHtml += `
            <button class="btn btn-outline-danger btn-sm" onclick="setInactive(${id})">
                Set Inactive
            </button>
        `;
    } else {
        actionHtml += '<span class="text-muted small">Inactive</span>';
    }

    row.querySelector('.member-name').innerHTML = `<strong>${escapeHtml(name)}</strong>`;
    row.querySelector('.member-status').innerHTML = `<span class="badge bg-${statusClass}">${status}</span>`;
    row.querySelector('td:last-child').innerHTML = actionHtml;

    if(borrowerDataTable){
        borrowerDataTable.row(row).invalidate().draw(false);
    }
}

function getMemberRow(id){
    let row = document.querySelector(`tr[data-member-id="${id}"]`);

    if(row || !borrowerDataTable){
        return row;
    }

    let foundRow = null;
    borrowerDataTable.rows().every(function(){
        let node = this.node();
        if(node && node.dataset.memberId === String(id)){
            foundRow = node;
        }
    });

    return foundRow;
}

function escapeHtml(value){
    return value.replace(/[&<>"']/g, function(character){
        return {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        }[character];
    });
}

function escapeJsString(value){
    return value.replace(/\\/g, '\\\\').replace(/'/g, "\\'");
}
</script>
<?php render_footer(); ?>
</body>
</html>
