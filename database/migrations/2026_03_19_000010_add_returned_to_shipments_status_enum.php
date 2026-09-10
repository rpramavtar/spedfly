<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(
            "ALTER TABLE shipments MODIFY COLUMN status ENUM('pending', 'in_transit', 'delivered', 'delayed', 'returned') NOT NULL"
        );
    }

    public function down(): void
    {
        DB::statement(
            "ALTER TABLE shipments MODIFY COLUMN status ENUM('pending', 'in_transit', 'delivered', 'delayed') NOT NULL"
        );
    }
};
