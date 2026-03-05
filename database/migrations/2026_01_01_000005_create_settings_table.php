<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSettingsTable extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table): void {
            $table->id();
            $table->decimal('approval_threshold', 15, 2)->default(10000000);
            $table->integer('sla_supervisor')->default(240);
            $table->integer('sla_manager')->default(360);
            $table->integer('sla_finance')->default(240);
            $table->time('workday_start')->default('08:00:00');
            $table->time('workday_end')->default('17:00:00');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
}
