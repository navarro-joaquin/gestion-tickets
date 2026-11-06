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
        Schema::table('tickets', function (Blueprint $table) {
            $table->foreignId('service_id')
                ->nullable()
                ->after('section_ticket_code_id')
                ->constrained()
                ->nullOnDelete();

            $table->string('service_label', 20)->nullable()->after('category_id');
            $table->boolean('is_preferential')->default(false)->after('service_label');
            $table->foreignId('priority_profile_id')
                ->nullable()
                ->after('is_preferential')
                ->constrained()
                ->nullOnDelete();
            $table->unsignedInteger('priority_score')->default(0)->after('priority_profile_id');
            $table->json('priority_snapshot')->nullable()->after('priority_score');

            $table->index(
                ['current_section_id', 'status', 'is_preferential', 'issued_at'],
                'tickets_queue_priority_index',
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropIndex('tickets_queue_priority_index');
            $table->dropConstrainedForeignId('service_id');
            $table->dropConstrainedForeignId('priority_profile_id');
            $table->dropColumn([
                'service_label',
                'is_preferential',
                'priority_score',
                'priority_snapshot',
            ]);
        });
    }
};
