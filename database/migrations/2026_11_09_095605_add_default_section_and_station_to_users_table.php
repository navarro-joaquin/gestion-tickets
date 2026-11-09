<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('default_section_id')
                ->nullable()
                ->after('is_active')
                ->constrained('sections')
                ->nullOnDelete();

            $table->foreignId('default_station_id')
                ->nullable()
                ->after('default_section_id')
                ->constrained('stations')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('default_station_id');
            $table->dropConstrainedForeignId('default_section_id');
        });
    }
};
