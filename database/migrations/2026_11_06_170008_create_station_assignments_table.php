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
        Schema::create('station_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('operator_session_id')->constrained()->cascadeOnDelete();
            // MySQL no permite ON DELETE CASCADE en columnas base de columnas
            // generadas almacenadas; stations se desactiva, no se elimina.
            $table->foreignId('station_id')->constrained();
            $table->timestamp('assigned_at');
            $table->timestamp('released_at')->nullable();
            $table->timestamps();

            $table->index(['operator_session_id', 'assigned_at']);
            $table->index(['station_id', 'released_at']);

            $table->unsignedBigInteger('active_station_key')->nullable()
                ->storedAs('case when released_at is null then station_id else null end');

            $table->unique('active_station_key');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('station_assignments');
    }
};
