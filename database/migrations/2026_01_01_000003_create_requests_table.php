<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateRequestsTable extends Migration
{
    public function up(): void
    {
        Schema::create('requests', function (Blueprint $table): void {
            $table->id();
            $table->string('request_number')->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('department_id')->constrained('departments')->cascadeOnDelete();
            $table->decimal('amount', 15, 2)->index();
            $table->text('description');
            $table->enum('status', ['draft', 'submitted', 'approved', 'rejected', 'completed'])->index();
            $table->unsignedTinyInteger('current_level')->default(1)->index();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'current_level', 'submitted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('requests');
    }
}
