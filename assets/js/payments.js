function recordPayment(paymentId, userId, memberName, amount) {
    if (confirm(`Record payment of K${amount.toFixed(2)} for ${memberName}?`)) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="record_payment">
            <input type="hidden" name="payment_id" value="${paymentId}">
            <input type="hidden" name="member_id" value="${userId}">
            <input type="hidden" name="amount" value="${amount}">
            <input type="hidden" name="status" value="paid">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

function markAsMissed(paymentId, userId) {
    if (confirm("Mark this payment as missed?")) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="record_payment">
            <input type="hidden" name="payment_id" value="${paymentId}">
            <input type="hidden" name="member_id" value="${userId}">
            <input type="hidden" name="amount" value="0">
            <input type="hidden" name="status" value="missed">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}