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
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('section_id')->constrained()->cascadeOnDelete();
            $table->string('slug', 80);
            $table->string('name', 120);
            $table->string('label', 20);
            $table->string('description', 255)->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('available_days')->nullable();
            $table->time('available_from')->nullable();
            $table->time('available_to')->nullable();
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['section_id', 'slug']);
            $table->index(['section_id', 'is_active', 'sort_order'], 'services_section_active_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};
