<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('control_tape_records', function (Blueprint $table) {
            $table->id();
            $table->string('cashbox_unique_number')->index()->comment('Заводской/серийный номер кассы');
            $table->bigInteger('shift_number')->index()->comment('Номер смены');
            $table->string('operation_type')->index()->comment('Тип операции ');
            $table->decimal('sum', 18, 2)->comment('Сумма чека');
            $table->dateTime('date')->comment('Дата выполнения операции');
            $table->bigInteger('employee_code')->nullable()->comment('Код сотрудника');
            $table->string('number')->nullable()->comment('Номер чека');
            $table->boolean('is_offline')->default(false)->comment('Признак автономного режима');
            $table->timestamps();

            $table->unique(['cashbox_unique_number', 'shift_number', 'operation_type', 'date']);
            $table->comment('Контрольная лента за смену');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('control_tape_records');
    }
};