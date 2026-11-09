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
        Schema::table('printers', function (Blueprint $table) {
            $table->dropForeign(['station_id']);
        });

        Schema::table('printers', function (Blueprint $table) {
            $table->dropIndex(['station_id', 'is_active']);
            $table->dropColumn('station_id');
        });

        Schema::table('printers', function (Blueprint $table) {
            $table->foreignId('kiosk_device_id')
                ->nullable()
                ->after('id')
                ->constrained()
                ->nullOnDelete();
            $table->index(['kiosk_device_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('printers', function (Blueprint $table) {
            $table->dropForeign(['kiosk_device_id']);
        });

        Schema::table('printers', function (Blueprint $table) {
            $table->dropIndex(['kiosk_device_id', 'is_active']);
            $table->dropColumn('kiosk_device_id');
        });

        Schema::table('printers', function (Blueprint $table) {
            $table->foreignId('station_id')
                ->nullable()
                ->after('id')
                ->constrained()
                ->nullOnDelete();
            $table->index(['station_id', 'is_active']);
        });
    }
};
