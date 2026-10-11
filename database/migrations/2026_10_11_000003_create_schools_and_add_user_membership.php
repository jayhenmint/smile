<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schools', function (Blueprint $table) {
            $table->increments('id');
            $table->text('deped_school_id')->nullable()->unique();
            $table->text('name');
            $table->text('division')->nullable();
            $table->text('region')->nullable();
            $table->text('address')->nullable();
            $table->timestampTz('created_at')->useCurrent();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->unsignedInteger('school_id')->nullable()->index();
            $table->foreign('school_id')->references('id')->on('schools')->restrictOnDelete();
            $table->text('phone')->nullable();
            $table->boolean('is_active')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['school_id']);
            $table->dropColumn(['school_id', 'phone', 'is_active']);
        });
        Schema::dropIfExists('schools');
    }
};
