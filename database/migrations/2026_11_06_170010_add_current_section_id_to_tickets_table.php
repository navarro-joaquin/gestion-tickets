<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->foreignId('current_section_id')
                ->nullable()
                ->after('section_id')
                ->constrained('sections');

            $table->index(['current_section_id', 'status', 'issued_at']);
        });

        DB::table('tickets')->update([
            'current_section_id' => DB::raw('section_id'),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropForeign(['current_section_id']);
            $table->dropIndex(['current_section_id', 'status', 'issued_at']);
            $table->dropColumn('current_section_id');
        });
    }
};
