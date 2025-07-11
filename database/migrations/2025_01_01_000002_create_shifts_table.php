<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cashbox_id')->comment('ID кассы')->constrained('cashboxes')->onDelete('cascade');
            $table->integer('shift_number')->comment('Номер смены');
            $table->dateTime('open_date')->comment('Дата и время открытия смены');
            $table->dateTime('close_date')->nullable()->comment('Дата и время закрытия смены');
            $table->timestamps();
            $table->unique(['cashbox_id', 'shift_number']);
            $table->comment('Смены');
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('shifts');
    }
};