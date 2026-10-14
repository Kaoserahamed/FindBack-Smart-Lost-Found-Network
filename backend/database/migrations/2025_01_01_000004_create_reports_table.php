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
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->foreignId('community_id')
                ->constrained('communities')
                ->cascadeOnDelete();
            $table->foreignId('category_id')
                ->constrained('categories')
                ->restrictOnDelete();
            $table->enum('type', ['lost', 'found']);
            $table->string('title');
            $table->text('description');
            $table->string('color')->nullable();
            $table->string('brand')->nullable();
            $table->timestamp('event_at');
            $table->string('location_name');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->enum('status', ['open', 'under_review', 'resolved', 'rejected', 'expired'])->default('open');
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            // Optimized for the common "browse reports in a community" queries.
            $table->index(['community_id', 'type', 'status']);
            $table->index('category_id');
            $table->index('event_at');
            $table->index('status');
            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};
