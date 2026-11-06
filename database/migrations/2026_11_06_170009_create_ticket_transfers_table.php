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
        Schema::create('ticket_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('from_section_id')->constrained('sections');
            $table->foreignId('to_section_id')->constrained('sections');
            $table->foreignId('from_operator_id')->nullable()->constrained('users');
            $table->foreignId('to_operator_id')->nullable()->constrained('users');
            $table->string('reason', 255)->nullable();
            $table->timestamp('transferred_at');
            $table->timestamps();

            $table->index(['ticket_id', 'transferred_at']);
            $table->index(['to_section_id', 'transferred_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ticket_transfers');
    }
};
