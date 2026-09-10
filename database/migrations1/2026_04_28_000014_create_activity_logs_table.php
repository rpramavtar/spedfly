<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->text('message');
            $table->string('type')->index();
            $table->string('module')->default('system')->index();
            $table->string('severity')->default('success')->index();
            $table->json('data')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'severity', 'created_at']);
            $table->index(['user_id', 'module', 'created_at']);
        });

        $now = now();
        $sourceRows = DB::table('app_notifications')
            ->orderBy('id')
            ->get(['user_id', 'title', 'message', 'type', 'data', 'created_at', 'updated_at']);

        $rows = [];

        foreach ($sourceRows as $row) {
            $type = strtolower((string) $row->type);
            $rows[] = [
                'user_id' => (int) $row->user_id,
                'title' => (string) $row->title,
                'message' => (string) $row->message,
                'type' => $type,
                'module' => match ($type) {
                    'csv_import' => 'imports',
                    'order_status' => 'orders',
                    'call_center' => 'calls',
                    'return_status' => 'returns',
                    default => 'system',
                },
                'severity' => match ($type) {
                    'call_center', 'return_status' => 'warning',
                    'payment_failed' => 'danger',
                    default => 'success',
                },
                'data' => $row->data,
                'created_at' => $row->created_at ?? $now,
                'updated_at' => $row->updated_at ?? $now,
            ];
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('activity_logs')->insert($chunk);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
