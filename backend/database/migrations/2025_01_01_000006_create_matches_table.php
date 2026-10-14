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
        Schema::create('matches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lost_report_id')
                ->constrained('reports')
                ->cascadeOnDelete();
            $table->foreignId('found_report_id')
                ->constrained('reports')
                ->cascadeOnDelete();
            $table->unsignedTinyInteger('score'); // 0-100 confidence
            $table->jsonb('reasons')->nullable();
            $table->enum('status', ['pending', 'accepted', 'dismissed'])->default('pending');
            $table->string('algorithm_version');
            $table->timestamps();

            // A given lost<->found pair can only be matched once.
            $table->unique(['lost_report_id', 'found_report_id']);
            $table->index('status');
            $table->index('score');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('matches');
    }
};
