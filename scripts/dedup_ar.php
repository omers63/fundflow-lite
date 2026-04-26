<?php
// Deduplicate ar.json while preserving the LAST value for each key
$json = file_get_contents(__DIR__ . '/../lang/ar.json');
$ar = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

if (!is_array($ar)) {
    echo "ERROR: Could not parse JSON\n";
    exit(1);
}

// Count total keys
$total = count($ar);

// Write back deduped version (json_decode already deduplicates, using last value)
$output = json_encode($ar, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

// Use 2-space indentation to match original format
file_put_contents(__DIR__ . '/../lang/ar.json', $output . "\n");

echo "Done. Total unique keys: {$total}\n";
