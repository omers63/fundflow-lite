<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FilamentNavigationGroupsTest extends TestCase
{
    /**
     * Canonical group keys allowed across both panels.
     *
     * @var array<int, string>
     */
    private array $allowedGroupKeys = [
        'app.nav.group.membership',
        'app.nav.group.finance',
        'app.nav.group.settings',
        'app.nav.group.system',
        'app.nav.group.my_finance',
        'app.nav.group.loans',
        'app.nav.group.account',
    ];

    #[Test]
    public function all_filament_navigation_group_methods_use_canonical_translation_keys(): void
    {
        $basePath = app_path('Filament');

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($basePath)
        );

        foreach ($iterator as $file) {
            if (! $file instanceof \SplFileInfo || $file->getExtension() !== 'php') {
                continue;
            }

            $contents = file_get_contents($file->getPathname());
            if ($contents === false || ! str_contains($contents, 'getNavigationGroup(): ?string')) {
                continue;
            }

            preg_match_all(
                '/public\s+static\s+function\s+getNavigationGroup\(\):\s+\?string\s*\{[\s\S]*?return\s+([^;]+);[\s\S]*?\}/',
                $contents,
                $matches
            );

            foreach ($matches[1] ?? [] as $returnExpression) {
                $expr = trim($returnExpression);

                if ($expr === 'null') {
                    continue;
                }

                $this->assertMatchesRegularExpression(
                    '/^__\(\s*\'([^\']+)\'\s*\)$/',
                    $expr,
                    "Non-canonical navigation group return in {$file->getPathname()}: {$expr}"
                );

                preg_match('/^__\(\s*\'([^\']+)\'\s*\)$/', $expr, $keyMatch);
                $groupKey = $keyMatch[1] ?? '';

                $this->assertContains(
                    $groupKey,
                    $this->allowedGroupKeys,
                    "Unexpected navigation group key in {$file->getPathname()}: {$groupKey}"
                );
            }
        }
    }

    #[Test]
    public function admin_and_member_panel_navigation_group_order_is_canonical(): void
    {
        $adminProvider = file_get_contents(app_path('Providers/Filament/AdminPanelProvider.php'));
        $memberProvider = file_get_contents(app_path('Providers/Filament/MemberPanelProvider.php'));

        $this->assertIsString($adminProvider);
        $this->assertIsString($memberProvider);

        preg_match_all('/NavigationGroup::make\(__\(\s*\'([^\']+)\'\s*\)\)/', $adminProvider, $adminMatches);
        preg_match_all('/NavigationGroup::make\(__\(\s*\'([^\']+)\'\s*\)\)/', $memberProvider, $memberMatches);

        $this->assertSame(
            [
                'app.nav.group.membership',
                'app.nav.group.finance',
                'app.nav.group.settings',
                'app.nav.group.system',
            ],
            $adminMatches[1] ?? [],
            'Admin panel navigation groups order changed from canonical order.'
        );

        $this->assertSame(
            [
                'app.nav.group.my_finance',
                'app.nav.group.loans',
                'app.nav.group.account',
                'app.nav.group.settings',
            ],
            $memberMatches[1] ?? [],
            'Member panel navigation groups order changed from canonical order.'
        );
    }
}

