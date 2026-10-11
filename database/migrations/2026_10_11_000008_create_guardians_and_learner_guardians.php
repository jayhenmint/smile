<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guardians', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedInteger('school_id');
            $table->foreign('school_id')->references('id')->on('schools')->restrictOnDelete();
            $table->foreignUuid('user_id')->nullable()->constrained()->restrictOnDelete();
            $table->text('full_name');
            $table->text('relationship');
            $table->text('phone')->nullable();
            $table->text('email')->nullable();
            $table->text('address')->nullable();
            $table->unique(['school_id', 'id']);
        });
        Schema::create('learner_guardians', function (Blueprint $table) {
            $table->unsignedInteger('school_id');
            $table->foreign('school_id')->references('id')->on('schools')->restrictOnDelete();
            $table->uuid('learner_id');
            $table->uuid('guardian_id');
            $table->boolean('is_primary')->default(false);
            $table->boolean('is_emergency')->default(false);
            $table->primary(['learner_id', 'guardian_id']);
            $table->foreign(['school_id', 'learner_id'])->references(['school_id', 'id'])->on('learners')->cascadeOnDelete();
            $table->foreign(['school_id', 'guardian_id'])->references(['school_id', 'id'])->on('guardians')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learner_guardians');
        Schema::dropIfExists('guardians');
    }
};
