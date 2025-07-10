<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ticket_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->comment('ID чека')->constrained('webkassa_tickets')->onDelete('cascade');
            $table->decimal('sum', 10, 2)->comment('Сумма платежа');
            $table->integer('payment_type')->nullable()->comment('Типа платежа');
            $table->string('payment_type_name')->nullable()->comment('Название типа платежа');
            $table->timestamps();
            $table->comment('Платежи');
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_payments');
    }
};