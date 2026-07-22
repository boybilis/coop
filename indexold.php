<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Loan Calculator</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/mobile.css">
<link rel="stylesheet" href="assets/css/theme.css">
    <style>
        table { margin-top: 15px; border-collapse: collapse; }
        td, th { border: 1px solid #ccc; padding: 8px; }
    </style>
</head>
<body class="bg-light">

<div class="container mt-4">
<h2>Loan Calculator (2% Monthly Interest)</h2>

<label>Borrower Name:</label><br>
<input type="text" id="name" class="form-control mb-2"><br>

<label>Amount:</label><br>
<input type="number" id="amount" class="form-control mb-2"><br>

<label>No. of Months (can be decimal):</label><br>
<input type="number" step="0.1" id="months" class="form-control mb-2"><br>

<label>Start Date:</label><br>
<input type="date" id="startDate" class="form-control mb-2"><br>

<button onclick="compute()" class="btn btn-primary">Compute</button>

<div id="result"></div>
</div>

<script>
function compute() {

    let name = document.getElementById("name").value;
    let amount = parseFloat(document.getElementById("amount").value);
    let months = parseFloat(document.getElementById("months").value);
    let startDate = new Date(document.getElementById("startDate").value);

    if (!name || isNaN(amount) || isNaN(months) || isNaN(startDate.getTime())) {
        alert("Please complete all fields correctly.");
        return;
    }

    // Interest computation
   let monthlyRate = 0.02;

let totalInterest = Math.round(amount * monthlyRate * months * 100) / 100;
let totalPayable = amount + totalInterest;

    // FIX: decimal months → proper payment count
    let totalPayments = Math.ceil(months * 2);

    let regularPayment = Math.floor(totalPayable / totalPayments);

    let schedule = "";
    let totalPaid = 0;

    for (let i = 1; i <= totalPayments; i++) {

        let payment;

        if (i === totalPayments) {
            payment = Math.round(totalPayable - totalPaid);
        } else {
            payment = regularPayment;
        }

        totalPaid += payment;

        // compute semi-monthly schedule (approx 15-day intervals)
        let dueDate = new Date(startDate);
        dueDate.setDate(dueDate.getDate() + ((i - 1) * 15));

        schedule += `
            <tr>
                <td>${i}</td>
                <td>₱${payment}</td>
                <td>${dueDate.toISOString().split('T')[0]}</td>
            </tr>
        `;
    }

    document.getElementById("result").innerHTML = `
        <b>Borrower:</b> ${name} <br>
        <b>Total Interest:</b> ₱${totalInterest.toFixed(2)} <br>
        <b>Total Payable:</b> ₱${totalPayable.toFixed(2)} <br>
        <b>Total Payments:</b> ${totalPayments} <br><br>

        <div class="table-responsive">
<table>
            <tr>
                <th>#</th>
                <th>Amount</th>
                <th>Due Date</th>
            </tr>
            ${schedule}
        </table>
</div>

        <br><b>Total Paid:</b> ₱${totalPaid}
    `;
}
</script>

</body>
</html>
