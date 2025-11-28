<?php
function generateGroupCode() {
    $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $code = '';
    for ($i = 0; $i < 6; $i++) {
        $code .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $code;
}

function calculateLoanTotal($amount, $interestRate) {
    return $amount + ($amount * $interestRate / 100);
}

function formatCurrency($amount) {
    return 'K ' . number_format($amount, 2);
}

function getMeetingFrequencyText($frequency) {
    $frequencies = [
        'weekly_once' => 'Once a week',
        'weekly_twice' => 'Twice a week',
        'monthly_thrice' => 'Three times a month'
    ];
    return $frequencies[$frequency] ?? 'Unknown';
}
?>