<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('laundry_records') || ! Schema::hasColumn('laundry_records', 'status')) {
            return;
        }

        Schema::table('laundry_records', function (Blueprint $table): void {
            $table->string('status', 64)
                ->default('PENDING')
                ->change();
        });
    }

    public function down(): void
    {
        // Intentionally non-destructive.

        // Do not shrink this field again because valid workflow
        // statuses may exceed the previous column length.
    }
};
