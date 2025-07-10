<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shift_id')->comment('ID смены')->constrained('shifts')->onDelete('cascade');
            $table->string('number')->unique()->comment('Номер чека (фискальный признак)');
            $table->bigInteger('order_number')->comment('Порядковый номер чека');
            $table->dateTime('date')->comment('Дата и время чека');
            $table->integer('operation_type')->comment('Тип операции ');
            $table->string('operation_type_text')->comment('Тип операции (текст)');
            $table->decimal('total', 10, 2)->comment('Сумма чека ');
            $table->decimal('discount', 10, 2)->comment('Скидка в тенге');
            $table->decimal('markup', 10, 2)->comment('Наценка');
            $table->boolean('sent_data')->default(false)->comment('Отправил данные');
            $table->dateTimeTz('date_sent_data')->nullable()->comment('Дата отправки данных');
            $table->boolean('received_data')->default(false)->comment('Полученные данные');
            $table->timestamps();

            $table->unique(['shift_id', 'number', 'order_number', 'date']);
            $table->comment('Контрольная лента за смену');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};