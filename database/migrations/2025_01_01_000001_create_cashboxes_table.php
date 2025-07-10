<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('cashboxes', function (Blueprint $table) {
            $table->id();
            $table->string('cashbox_unique_number')->unique()->comment('Заводской/серийный номер кассы');;
            $table->string('xin')->comment('БИН/ИИН организации, которой принадлежит касса');
            $table->string('organization_name')->comment('Наименование организации, которой принадлежит касса');
            $table->timestamps();
            $table->comment('Кассы');
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('cashboxes');
    }
};