<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->foreignId('department_id')
                ->nullable()
                ->after('id')
                ->constrained('departments')
                ->nullOnDelete();

            $table->string('role', 20)
                ->default('staff')
                ->after('password')
                ->index();

            $table->boolean('is_active')
                ->default(true)
                ->after('role');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropForeign(['department_id']);
            $table->dropColumn(['department_id', 'role', 'is_active']);
        });
    }
};
