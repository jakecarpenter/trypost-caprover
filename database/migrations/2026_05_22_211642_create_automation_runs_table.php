<?php

declare(strict_types=1);

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
        Schema::create('automation_runs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('automation_id')->constrained('automations')->cascadeOnDelete();
            $table->foreignUuid('root_run_id')->nullable();
            $table->foreignUuid('trigger_item_id')->nullable()->constrained('automation_trigger_items')->nullOnDelete();
            $table->string('current_node_id')->nullable();
            $table->string('status')->default('pending');
            $table->boolean('is_manual')->default(false);
            $table->boolean('is_dry_run')->default(false);
            $table->timestamp('next_action_at')->nullable();
            $table->foreignUuid('generated_post_id')->nullable()->constrained('posts')->nullOnDelete();
            $table->json('context')->nullable();
            $table->json('error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['automation_id', 'status']);
            $table->index(['status', 'next_action_at']);
        });

        Schema::table('automation_runs', function (Blueprint $table) {
            $table->foreign('root_run_id')->references('id')->on('automation_runs')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('automation_runs');
    }
};
