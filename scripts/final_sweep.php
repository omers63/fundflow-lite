<?php
/**
 * Final comprehensive sweep for missing Arabic translations.
 * Scans all Blade views, PHP files, and compares against ar.json.
 */

$arJson  = file_get_contents(__DIR__ . '/../lang/ar.json');
$ar      = json_decode($arJson, true);
$arKeys  = array_keys($ar);

// ── 1. Collect all __('...') and trans('...') calls from Blade & PHP ─────────

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
        $ext = $file->getExtension();
        if (!in_array($ext, ['php', 'blade'])) {
            // Also allow .blade.php
            if (!str_ends_with($file->getFilename(), '.blade.php')) continue;
        }
        $content = file_get_contents($file->getPathname());

        // __('key'), trans('key'), __("key"), trans("key")
        preg_match_all('/(?:__|\btrans)\s*\(\s*[\'"](.+?)[\'"]/u', $content, $matches);
        foreach ($matches[1] as $key) {
            $found[$key] = true;
        }

        // @lang('key')
        preg_match_all('/@lang\s*\(\s*[\'"](.+?)[\'"]/u', $content, $matches2);
        foreach ($matches2[1] as $key) {
            $found[$key] = true;
        }
    }
}

// ── 2. Identify which found keys are missing from ar.json ────────────────────

$missing = [];
foreach (array_keys($found) as $key) {
    // Skip namespaced keys (e.g., app.something, validation.required)
    if (str_contains($key, '.') && !str_starts_with($key, 'passwords.') && !str_starts_with($key, 'pagination.')) {
        // Likely a namespaced key — not in ar.json
        continue;
    }
    // Skip keys that are clearly placeholders or variables
    if (str_contains($key, '$') || str_contains($key, '{') || strlen($key) > 200) {
        continue;
    }
    // Skip SAR currency key (intentional English)
    if ($key === 'SAR') continue;

    if (!array_key_exists($key, $ar)) {
        $missing[] = $key;
    }
}

sort($missing);

echo "=== FINAL SWEEP RESULTS ===\n";
echo "Total ar.json keys: " . count($arKeys) . "\n";
echo "Total __() calls found: " . count($found) . "\n";
echo "Missing from ar.json: " . count($missing) . "\n\n";

if ($missing) {
    echo "--- MISSING KEYS ---\n";
    foreach ($missing as $k) {
        echo "  " . $k . "\n";
    }
} else {
    echo "✅ All translation keys are covered!\n";
}

// ── 3. Also check for hardcoded English strings in Blade views ───────────────

echo "\n=== CHECKING PDF VIEWS FOR REMAINING HARDCODED ENGLISH ===\n";
$pdfDir = __DIR__ . '/../resources/views/pdf';
foreach (glob($pdfDir . '/*.blade.php') as $file) {
    $content = file_get_contents($file);
    $name = basename($file);
    // Look for plain text between > and < that looks like English (not Blade/PHP)
    preg_match_all('/>([A-Z][a-zA-Z\s\-:]+[a-z])</', $content, $m);
    $suspects = array_filter($m[1], fn($s) => strlen(trim($s)) > 3 && !str_contains($s, '{{'));
    if ($suspects) {
        echo "  [$name] Possible hardcoded English:\n";
        foreach (array_unique($suspects) as $s) {
            echo "    -> " . trim($s) . "\n";
        }
    } else {
        echo "  [$name] ✅ Clean\n";
    }
}

// ── 4. Check Filament pages & resources for missing labels ─────────────────

echo "\n=== CHECKING FOR HARDCODED ENGLISH IN FILAMENT RESOURCES ===\n";
$filamentDirs = [
    __DIR__ . '/../app/Filament/Admin/Resources',
    __DIR__ . '/../app/Filament/Member/Resources',
    __DIR__ . '/../app/Filament/Admin/Pages',
    __DIR__ . '/../app/Filament/Member/Pages',
];

$hardcodedLabels = [];
foreach ($filamentDirs as $dir) {
    if (!is_dir($dir)) continue;
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
    foreach ($it as $file) {
        if (!$file->isFile() || $file->getExtension() !== 'php') continue;
        $content = file_get_contents($file->getPathname());
        // Look for ->label('...') ->heading('...') ->title('...') with plain English (no __)
        preg_match_all('/->(?:label|heading|title|placeholder|hint|helperText|description|emptyStateHeading|emptyStateDescription)\s*\(\s*\'([A-Z][a-zA-Z0-9\s\-:\/]+)\'\s*\)/u', $content, $m);
        foreach ($m[1] as $label) {
            if (strlen($label) > 2 && !array_key_exists($label, $ar)) {
                $hardcodedLabels[] = ['file' => str_replace(__DIR__ . '/../', '', $file->getPathname()), 'label' => $label];
            }
        }
    }
}

if ($hardcodedLabels) {
    $grouped = [];
    foreach ($hardcodedLabels as $item) {
        $grouped[$item['file']][] = $item['label'];
    }
    $c = 0;
    foreach ($grouped as $file => $labels) {
        echo "  [$file]:\n";
        foreach (array_unique($labels) as $l) {
            echo "    -> $l\n";
            $c++;
        }
    }
    echo "\n  Total hardcoded labels NOT in ar.json: $c\n";
} else {
    echo "  ✅ No hardcoded labels found outside ar.json\n";
}
