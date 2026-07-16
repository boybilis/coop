<!DOCTYPE html>
<html>
<head>
    <title>Loan Calculator</title>
    <style>
        body { font-family: Arial; padding: 20px; }
        input, button { padding: 8px; margin: 5px; }
        table { margin-top: 15px; border-collapse: collapse; }
        td, th { border: 1px solid #ccc; padding: 8px; }
    </style>
</head>
<body>

<h2>Loan Calculator (2% Monthly Interest)</h2>

<label>Borrower Name:</label><br>
<input type="text" id="name"><br>

<label>Amount:</label><br>
<input type="number" id="amount"><br>

<label>No. of Months (can be decimal):</label><br>
<input type="number" step="0.1" id="months"><br>

<label>Start Date:</label><br>
<input type="date" id="startDate"><br>

<button onclick="compute()">Compute</button>

<div id="result"></div>

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

        <table>
            <tr>
                <th>#</th>
                <th>Amount</th>
                <th>Due Date</th>
            </tr>
            ${schedule}
        </table>

        <br><b>Total Paid:</b> ₱${totalPaid}
    `;
}
</script>

</body>
</html>