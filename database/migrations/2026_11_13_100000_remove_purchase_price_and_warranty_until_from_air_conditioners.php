<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('air_conditioners', function (Blueprint $table) {
            $table->dropColumn(['purchase_price', 'warranty_until']);
        });
    }

    public function down(): void
    {
        Schema::table('air_conditioners', function (Blueprint $table) {
            $table->decimal('purchase_price', 12, 2)->nullable();
            $table->date('warranty_until')->nullable();
        });
    }
};
