<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ticket_positions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->comment('ID чека')->constrained('webkassa_tickets')->onDelete('cascade');
            $table->string('position_name')->comment('Наименование позиции');
            $table->decimal('count', 10, 2)->default(0)->comment('Количество');
            $table->decimal('price', 10, 2)->default(0)->comment('Цена');
            $table->decimal('discount_tenge', 10, 2)->default(0)->comment('Скидка (в тенеге) ');
            $table->decimal('markup', 10, 2)->default(0)->comment('Наценка');
            $table->decimal('sum', 10, 2)->default(0)->comment('Сумма');
            $table->timestamps();
            $table->comment('Позиция товара/услуги в чеке');
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_positions');
    }
};