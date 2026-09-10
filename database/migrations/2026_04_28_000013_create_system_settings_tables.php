<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_settings', function (Blueprint $table) {
            $table->id();
            $table->string('setting_key')->unique();
            $table->text('setting_value')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('updated_by');
        });

        Schema::create('system_setting_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('system_setting_id')->constrained('system_settings')->cascadeOnDelete();
            $table->string('setting_key');
            $table->string('setting_label');
            $table->text('previous_value')->nullable();
            $table->text('new_value')->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('applied');
            $table->timestamps();

            $table->index(['setting_key', 'created_at']);
            $table->index(['changed_by', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_setting_histories');
        Schema::dropIfExists('system_settings');
    }
};
