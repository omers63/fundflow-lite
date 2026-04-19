<?php

use App\Models\BankImportTemplate;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('bank_import_templates')) {
            return;
        }

        BankImportTemplate::query()->withTrashed()->each(function (BankImportTemplate $template): void {
            $opt = $template->optional_columns;
            if (! is_array($opt)) {
                $opt = [];
            }

            $existingKeys = collect($opt)
                ->map(fn ($def) => is_array($def) ? trim((string) ($def['key'] ?? '')) : '')
                ->filter()
                ->all();

            if (filled($template->reference_column ?? null) && ! in_array('reference', $existingKeys, true)) {
                $opt[] = ['key' => 'reference', 'column' => $template->reference_column];
            }
            if (filled($template->description_column ?? null) && ! in_array('description', $existingKeys, true)) {
                $opt[] = ['key' => 'description', 'column' => $template->description_column];
            }
            if (filled($template->balance_column ?? null) && ! in_array('balance', $existingKeys, true)) {
                $opt[] = ['key' => 'balance', 'column' => $template->balance_column];
            }

            $template->optional_columns = $opt;
            $template->saveQuietly();
        });

        Schema::table('bank_import_templates', function (Blueprint $table): void {
            if (Schema::hasColumn('bank_import_templates', 'description_column')) {
                $table->dropColumn('description_column');
            }
            if (Schema::hasColumn('bank_import_templates', 'reference_column')) {
                $table->dropColumn('reference_column');
            }
            if (Schema::hasColumn('bank_import_templates', 'balance_column')) {
                $table->dropColumn('balance_column');
            }
        });
    }

    public function down(): void
    {
        Schema::table('bank_import_templates', function (Blueprint $table): void {
            if (! Schema::hasColumn('bank_import_templates', 'description_column')) {
                $table->string('description_column')->nullable();
            }
            if (! Schema::hasColumn('bank_import_templates', 'reference_column')) {
                $table->string('reference_column')->nullable();
            }
            if (! Schema::hasColumn('bank_import_templates', 'balance_column')) {
                $table->string('balance_column')->nullable()->after('reference_column');
            }
        });
    }
};
