<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('repeated_tickets', function (Blueprint $table) {
            $table->id();
            $table->string('login')->comment('Логин кассы');
            $table->date('from')->comment('Дата начала загрузки');
            $table->date('to')->nullable()->comment('Дата конца загрузки');
            $table->timestamps();

            $table->unique(['login', 'from']); // составной уникальный ключ
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('repeated_tickets');
    }
};