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
        if (Schema::hasColumn('tickets', 'active_station_key')) {
            Schema::table('tickets', function (Blueprint $table) {
                $table->dropUnique(['active_station_key']);
                $table->dropColumn('active_station_key');
            });
        }

        Schema::table('tickets', function (Blueprint $table) {
            $table->string('active_station_key', 100)->nullable()
                ->storedAs("case when status in ('called', 'in_service') then concat(current_section_id, ':', station_label) else null end");

            $table->unique('active_station_key');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('tickets', 'active_station_key')) {
            Schema::table('tickets', function (Blueprint $table) {
                $table->dropUnique(['active_station_key']);
                $table->dropColumn('active_station_key');
            });
        }

        Schema::table('tickets', function (Blueprint $table) {
            $table->string('active_station_key', 50)->nullable()
                ->storedAs("case when status in ('called', 'in_service') then station_label else null end");

            $table->unique('active_station_key');
        });
    }
};
