<?php
$lines = file(__DIR__ . '/../lang/ar.json');
for ($i = 2070; $i < 2100; $i++) {
    if (isset($lines[$i])) {
        echo ($i + 1) . ': ' . $lines[$i];
    }
}
