<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_types', function (Blueprint $table) {
            $table->increments('id');
            $table->text('name')->unique();
            $table->enum('required_for', ['enrollment', 'mainstreaming', 'both']);
            $table->enum('from_party', ['parent', 'school']);
        });
        DB::table('document_types')->insert([
            ['name' => 'Enrollment form', 'required_for' => 'enrollment', 'from_party' => 'parent'],
            ['name' => 'PSA birth certificate', 'required_for' => 'enrollment', 'from_party' => 'parent'],
            ['name' => 'Previous report card / SF9 / SF10', 'required_for' => 'enrollment', 'from_party' => 'parent'],
            ['name' => 'Medical/diagnostic report', 'required_for' => 'enrollment', 'from_party' => 'parent'],
            ['name' => 'Parental consent (enrollment, data privacy, media)', 'required_for' => 'enrollment', 'from_party' => 'parent'],
            ['name' => 'PWD ID (optional)', 'required_for' => 'enrollment', 'from_party' => 'parent'],
            ['name' => 'Prior IEP (if any)', 'required_for' => 'enrollment', 'from_party' => 'parent'],
            ['name' => 'Written request for regular class', 'required_for' => 'mainstreaming', 'from_party' => 'parent'],
            ['name' => 'Signed transition plan consent', 'required_for' => 'mainstreaming', 'from_party' => 'parent'],
            ['name' => 'Updated assessment (if required)', 'required_for' => 'mainstreaming', 'from_party' => 'parent'],
            ['name' => 'Updated IEP / progress summary', 'required_for' => 'mainstreaming', 'from_party' => 'school'],
            ['name' => 'SPED teacher recommendation', 'required_for' => 'mainstreaming', 'from_party' => 'school'],
            ['name' => 'IEP team meeting record', 'required_for' => 'mainstreaming', 'from_party' => 'school'],
            ['name' => 'Placement decision record', 'required_for' => 'enrollment', 'from_party' => 'school'],
            ['name' => 'SPED assessment form (may follow non-graded enrollment; required for graded level change)', 'required_for' => 'both', 'from_party' => 'school'],
            ['name' => 'SDO approval for non-graded to graded change', 'required_for' => 'mainstreaming', 'from_party' => 'school'],
            ['name' => 'Height/weight record (baseline)', 'required_for' => 'enrollment', 'from_party' => 'school'],
        ]);
        Schema::create('learner_documents', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('school_id');
            $table->foreign('school_id')->references('id')->on('schools')->restrictOnDelete();
            $table->uuid('learner_id');
            $table->foreign(['school_id', 'learner_id'])->references(['school_id', 'id'])->on('learners')->cascadeOnDelete();
            $table->unsignedInteger('document_type_id');
            $table->foreign('document_type_id')->references('id')->on('document_types')->restrictOnDelete();
            $table->enum('status', ['missing', 'submitted', 'verified', 'expired'])->default('missing');
            $table->text('file_url')->nullable();
            $table->date('submitted_on')->nullable();
            $table->uuid('verified_by')->nullable();
            $table->foreign(['school_id', 'verified_by'])->references(['school_id', 'id'])->on('users')->restrictOnDelete();
            $table->text('notes')->nullable(); // Encrypted: may describe medical documents.
            $table->unique(['learner_id', 'document_type_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learner_documents');
        Schema::dropIfExists('document_types');
    }
};
