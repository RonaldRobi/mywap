<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('membership_fees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->year('year');
            $table->decimal('amount', 10, 2)->default(0);
            $table->string('status')->default('unpaid');
            $table->timestamp('paid_at')->nullable();
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('membership_fees');
    }
};
