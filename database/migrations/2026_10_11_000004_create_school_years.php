<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('school_years', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('school_id');
            $table->foreign('school_id')->references('id')->on('schools')->restrictOnDelete();
            $table->text('label');
            $table->date('start_date');
            $table->date('end_date');
            $table->boolean('is_current')->default(false);
            $table->unique(['school_id', 'label']);
        });
        DB::statement('CREATE UNIQUE INDEX school_years_one_current_per_school ON school_years (school_id) WHERE is_current');
    }

    public function down(): void
    {
        Schema::dropIfExists('school_years');
    }
};
