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
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('section_id')->constrained()->cascadeOnDelete();
            $table->foreignId('section_ticket_code_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->char('code', 1);
            $table->date('period_key');
            $table->unsignedInteger('number');
            $table->string('status', 20)->default('waiting');
            $table->string('station_label', 50)->nullable();
            // MySQL no permite SET NULL en una columna base de una columna generada
            // almacenada; los usuarios se desactivan, no se eliminan.
            $table->foreignId('operator_id')->nullable()->constrained('users');
            $table->timestamp('issued_at');
            $table->timestamp('called_at')->nullable();
            $table->unsignedTinyInteger('call_count')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->unique(['section_id', 'code', 'period_key', 'number']);
            $table->index(['section_id', 'status', 'issued_at']);
            $table->index(['section_id', 'category_id', 'issued_at']);

            $table->string('active_station_key', 50)->nullable()
                ->storedAs("case when status in ('called', 'in_service') then station_label else null end");
            $table->unsignedBigInteger('active_operator_key')->nullable()
                ->storedAs("case when status in ('called', 'in_service') then operator_id else null end");

            $table->unique('active_station_key');
            $table->unique('active_operator_key');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
