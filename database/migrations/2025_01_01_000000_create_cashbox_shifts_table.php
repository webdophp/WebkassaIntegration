<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('cashbox_shifts', function (Blueprint $table) {
            $table->id();
            $table->string('cashbox_unique_number')->index()->comment('Заводской/серийный номер кассы');;
            $table->bigInteger('shift_number')->index()->comment('Номер смены');
            $table->timestamps();
            $table->comment('Кассовые смены');
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('cashbox_shifts');
    }
};