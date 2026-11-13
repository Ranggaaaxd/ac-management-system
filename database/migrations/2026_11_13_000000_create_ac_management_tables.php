<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('buildings', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('address')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('floors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('building_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->integer('floor_number');
            $table->timestamps();
        });

        Schema::create('rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('floor_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('technicians', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('spare_parts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('category', ['freon', 'kapasitor', 'pipa', 'lainnya']);
            $table->string('unit');
            $table->decimal('stock', 8, 2)->default(0);
            $table->decimal('minimum_stock', 8, 2)->default(1);
            $table->timestamps();
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number')->unique();
            $table->foreignId('building_id')->nullable()->constrained()->nullOnDelete();
            $table->date('invoice_date');
            $table->date('due_date')->nullable();
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->enum('payment_status', ['unpaid', 'paid', 'canceled'])->default('unpaid');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('air_conditioners', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->constrained()->cascadeOnDelete();
            $table->string('asset_code')->unique();
            $table->string('brand');
            $table->enum('ac_type', ['split_wall', 'cassette', 'standing_floor', 'split_duct', 'vrv_vrf'])->default('split_wall');
            $table->string('capacity_pk');
            $table->string('refrigerant_type')->default('R32');
            $table->year('production_year')->nullable();
            $table->decimal('purchase_price', 12, 2)->nullable();
            $table->date('warranty_until')->nullable();
            $table->enum('condition', ['baik', 'rusak_ringan', 'rusak_berat', 'perlu_servis'])->default('baik');
            $table->date('last_serviced_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('asset_mutations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('air_conditioner_id')->constrained()->cascadeOnDelete();
            $table->foreignId('from_room_id')->constrained('rooms')->cascadeOnDelete();
            $table->foreignId('to_room_id')->constrained('rooms')->cascadeOnDelete();
            $table->date('mutation_date');
            $table->text('reason')->nullable();
            $table->string('transferred_by')->nullable();
            $table->timestamps();
        });

        Schema::create('maintenance_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('air_conditioner_id')->constrained()->cascadeOnDelete();
            $table->foreignId('technician_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('type', ['pemasangan_baru', 'pengecekan', 'perawatan_berkala', 'perbaikan']);
            $table->text('issue_description')->nullable();
            $table->text('action_taken')->nullable();
            $table->decimal('labor_cost', 12, 2)->default(0);
            $table->boolean('is_warranty_claim')->default(false);
            $table->decimal('pressure_psi', 6, 2)->nullable();
            $table->decimal('current_ampere', 6, 2)->nullable();
            $table->decimal('blow_temp_celsius', 4, 2)->nullable();
            $table->decimal('voltage', 6, 2)->nullable();
            $table->string('before_photo')->nullable();
            $table->string('after_photo')->nullable();
            $table->enum('status', ['draft', 'selesai'])->default('draft');
            $table->date('performed_at');
            $table->timestamps();
        });

        Schema::create('maintenance_spare_parts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('maintenance_record_id')->constrained()->cascadeOnDelete();
            $table->foreignId('spare_part_id')->constrained()->cascadeOnDelete();
            $table->decimal('quantity', 8, 2)->default(1);
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_spare_parts');
        Schema::dropIfExists('maintenance_records');
        Schema::dropIfExists('asset_mutations');
        Schema::dropIfExists('air_conditioners');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('spare_parts');
        Schema::dropIfExists('technicians');
        Schema::dropIfExists('rooms');
        Schema::dropIfExists('floors');
        Schema::dropIfExists('buildings');
    }
};
