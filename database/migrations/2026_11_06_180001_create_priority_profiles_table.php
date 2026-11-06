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
        Schema::create('priority_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80)->unique();
            $table->string('description', 255)->nullable();
            $table->unsignedTinyInteger('general_ratio')->default(2);
            $table->unsignedTinyInteger('preferential_ratio')->default(1);
            $table->unsignedSmallInteger('aging_minutes')->default(30);
            $table->unsignedTinyInteger('max_consecutive_preferential')->default(1);
            $table->boolean('is_active')->default(true);
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->timestamps();

            $table->index(['is_active', 'effective_from', 'effective_to'], 'priority_profiles_effective_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('priority_profiles');
    }
};
