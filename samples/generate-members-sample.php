<?php

/** One-off generator: php samples/generate-members-sample.php */
$firstNames = [
    'Ahmed', 'Fatima', 'Omar', 'Layla', 'Khalid', 'Noura', 'Youssef', 'Hanan', 'Ibrahim', 'Mariam',
    'Saeed', 'Reem', 'Hassan', 'Amal', 'Tariq', 'Dina', 'Walid', 'Salma', 'Faisal', 'Huda',
    'Nasser', 'Rania', 'Majed', 'Lina', 'Bandar', 'Ghada', 'Turki', 'Nada', 'Sultan', 'Hessa',
    'Abdullah', 'Mona', 'Rashed', 'Sara', 'Meshal', 'Wafa', 'Fahad', 'Aisha', 'Talal', 'Basma',
    'Saud', 'Nouf', 'Khaled', 'Rawan', 'Anwar', 'Lama', 'Sami', 'Jawaher', 'Hamad', 'Dalal',
];
$lastNames = [
    'Al-Mutairi', 'Al-Otaibi', 'Al-Ghamdi', 'Al-Zahrani', 'Al-Dosari', 'Al-Harbi', 'Al-Qahtani', 'Al-Shammari', 'Al-Subaie', 'Al-Rashid',
];
$statusCycle = ['active', 'active', 'active', 'suspended', 'delinquent', 'active'];
$contribCycle = [500, 500, 1000, 1000, 1500, 2000, 2500, 3000];

$path = __DIR__.'/members-import-sample.csv';
$out = fopen($path, 'w');

$csv = static function ($handle, array $fields): void {
    fputcsv($handle, $fields, ',', '"', '\\');
};

$headers = [
    'name', 'email', 'password', 'phone', 'joined_at', 'status',
    'monthly_contribution_amount', 'parent_member_number', 'cash_balance', 'fund_balance',
];
$csv($out, $headers);

for ($i = 1; $i <= 50; $i++) {
    // Row 50: duplicate email (member010) — balance-only update; other columns ignored
    if ($i === 50) {
        $csv($out, [
            '',
            'member010@fundflow-import.example',
            '',
            '',
            '',
            '',
            '',
            '',
            '3200',
            '-750.25',
        ]);

        continue;
    }

    $fn = $firstNames[$i - 1];
    $ln = $lastNames[$i % count($lastNames)];
    $name = "{$fn} {$ln}";
    $email = sprintf('member%03d@fundflow-import.example', $i);
    // Every 7th row: empty password → default password from import modal
    $pwd = ($i % 7 === 0) ? '' : 'ImportRow'.$i.'Pwd!';
    $phone = sprintf('+96650%07d', 1_000_000 + $i * 137);
    $joined = date('Y-m-d', strtotime('2024-01-15 +'.($i * 11).' days'));
    $st = $statusCycle[$i % count($statusCycle)];
    $mc = $contribCycle[$i % count($contribCycle)];
    $parent = '';

    $cash = round(($i % 5) * 250.5 + ($i % 3) * 100, 2);
    if ($i % 11 === 0) {
        $cash = 0;
    }
    if ($i === 23) {
        $cash = 15000.75;
    }
    if ($i === 37) {
        $cash = 0.01;
    }

    $fund = round(($i % 6 - 2) * 450.25, 2);
    if ($i === 12) {
        $fund = -8500;
    }
    if ($i === 19) {
        $fund = 12000;
    }
    if ($i === 31) {
        $fund = -1250.5;
    }
    if ($i === 44) {
        $fund = 0;
    }

    $csv($out, [
        $name,
        $email,
        $pwd,
        $phone,
        $joined,
        $st,
        (string) $mc,
        $parent,
        (string) $cash,
        (string) $fund,
    ]);
}

fclose($out);

echo "Wrote {$path} (49 new members + 1 balance-adjustment row for existing member010).\n";
