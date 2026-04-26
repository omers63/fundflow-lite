<?php
// Find REAL missing translation keys - filtering out HTML/code fragments
$ar = json_decode(file_get_contents(__DIR__ . '/../lang/ar.json'), true);

$scanDirs = [
    __DIR__ . '/../resources/views',
    __DIR__ . '/../app/Filament',
    __DIR__ . '/../app/Livewire',
    __DIR__ . '/../app/Support',
    __DIR__ . '/../app/Models',
];

$found = [];

foreach ($scanDirs as $dir) {
    if (!is_dir($dir)) continue;
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
    foreach ($it as $file) {
        if (!$file->isFile()) continue;
        $fn = $file->getFilename();
        $ext = $file->getExtension();
        if ($ext !== 'php' && !str_ends_with($fn, '.blade.php')) continue;
        $content = file_get_contents($file->getPathname());

        preg_match_all('/(?:__|\btrans)\s*\(\s*[\'"](.+?)[\'"]/u', $content, $matches);
        foreach ($matches[1] as $key) {
            $found[$key] = true;
        }
        preg_match_all('/@lang\s*\(\s*[\'"](.+?)[\'"]/u', $content, $matches2);
        foreach ($matches2[1] as $key) {
            $found[$key] = true;
        }
    }
}

$missing = [];
foreach (array_keys($found) as $key) {
    // Skip namespaced keys
    if (preg_match('/^[a-z_]+\.[a-z_]+/', $key)) continue;
    // Skip SAR (intentional English) and very short keys
    if ($key === 'SAR' || strlen($key) < 2) continue;
    // Skip keys with HTML tags, PHP variables, unusual chars
    if (str_contains($key, '<') || str_contains($key, '$') || str_contains($key, '{') || str_contains($key, '\\')) continue;
    // Skip keys longer than 200 chars (likely template strings)
    if (strlen($key) > 200) continue;
    // Skip keys that are clearly example/placeholder data (phone numbers, etc)
    if (preg_match('/^\+?\d[\d\s]+$/', $key)) continue;
    // Skip keys with backslash sequences (escaped strings)
    if (str_contains($key, "\\n") || str_contains($key, "\\t")) continue;
    
    if (!array_key_exists($key, $ar)) {
        $missing[] = $key;
    }
}

sort($missing);

echo "Real missing keys: " . count($missing) . "\n\n";
foreach ($missing as $k) {
    echo json_encode($k) . "\n";
}
