<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE rollers RENAME COLUMN dictionary TO genes_dict');
        DB::statement('ALTER TABLE rollers RENAME COLUMN phenos TO pheno_dict');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE rollers RENAME COLUMN genes_dict TO dictionary');
        DB::statement('ALTER TABLE rollers RENAME COLUMN pheno_dict TO phenos');
    }
};
