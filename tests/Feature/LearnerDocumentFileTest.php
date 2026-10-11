<?php

use App\Actions\AuditLearner;
use App\Models\Learner;
use App\Models\LearnerDocument;
use App\Models\SchoolYear;
use App\Models\Section;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Activitylog\ActivityLogStatus;

beforeEach(function () {
    $this->withoutVite();
    $this->seed(RolesSeeder::class);
    $this->teacher = User::factory()->create();
    $this->teacher->assignRole(['sped_teacher', 'school_admin']);
    $this->section = Section::factory()->create(['school_id' => $this->teacher->school_id,
        'school_year_id' => SchoolYear::factory()->create(['school_id' => $this->teacher->school_id])->id, 'adviser_id' => $this->teacher->id]);
    $this->actingAs($this->teacher)->post('/learners', ['first_name' => 'Fictional', 'last_name' => 'Example', 'birthdate' => '2015-05-03',
        'lrn_status' => 'pending', 'health_notes' => 'Fictional initial health note',
        'section_id' => $this->section->id, 'enrolled_on' => '2026-10-11', 'is_transferee' => false, 'consent_confirmed' => true])->assertSessionHasNoErrors();
    $this->learner = Learner::query()->firstOrFail();
    $this->url = '/learners/'.$this->learner->id;
    $this->data = ['first_name' => 'Fictional Updated', 'last_name' => 'Example', 'birthdate' => '2015-05-03',
        'middle_name' => 'Test', 'sex' => 'F', 'address' => 'Fictional address', 'mother_tongue' => 'Test language', 'health_notes' => 'Fictional changed health note'];
});

beforeEach(function () {
    $this->docUrl = $this->url.'/documents';
    $this->docData = ['status' => 'submitted', 'submitted_on' => '2026-10-11', 'notes' => 'Fictional confidential medical note'];
});

beforeEach(function () {
    // Concurrent SQLite/PostgreSQL suites must not clear each other's fake files.
    Storage::set('learner_documents', Storage::fake('learner_documents_test_'.getmypid(), ['throw' => true, 'visibility' => 'private']));
    $this->put($this->docUrl.'/4', $this->docData)->assertSessionHasNoErrors();
    $this->document = LearnerDocument::query()->firstOrFail();
    $this->fileUrl = $this->docUrl.'/files/'.$this->document->id;
    $this->fixtures = [];
    $this->realFile = function ($name, $content) {
        $path = tempnam(sys_get_temp_dir(), 'smile-test-');
        file_put_contents($path, $content);
        $this->fixtures[] = $path;

        return new UploadedFile($path, $name, null, null, true);
    };
    $this->file = fn () => ($this->realFile)('Fictional-private-diagnosis.pdf', "%PDF-1.4\nFictional PDF test content\n%%EOF");
    $this->signed = fn ($expiry = null, $actor = null, $learner = null) => URL::temporarySignedRoute('learners.documents.files.download', $expiry ?? now()->addMinutes(5),
        ['learner' => $learner ?? $this->learner->id, 'document' => $this->document->id, 'user' => $actor ?? $this->teacher->id]);
});

afterEach(function () {
    foreach ($this->fixtures as $path) {
        if (file_exists($path)) {
            unlink($path);
        }
    }
});

test('attachment upload stores a random private path and signed download streams only after audited authorization', function () {
    $this->freezeTime();
    $original = (array) DB::table('learner_documents')->first();
    $this->post($this->fileUrl, ['file' => ($this->file)()])->assertRedirect($this->docUrl)->assertSessionHas('document_uploaded', true);
    $path = $this->document->fresh()->file_url;
    expect($path)->toStartWith($this->teacher->school_id.'/'.$this->learner->id.'/')->toEndWith('.pdf')->not->toContain('Fictional');
    Storage::disk('learner_documents')->assertExists($path);
    Storage::disk('public')->assertMissing($path);
    $updated = (array) DB::table('learner_documents')->first();
    unset($updated['file_url'], $original['file_url']);
    expect($updated)->toBe($original);
    $this->get($this->docUrl)->assertInertia(fn (Assert $page) => $page->where('items.3.has_file', true)->where('items.3.record_id', $this->document->id)
        ->where('items.3.download_url', ($this->signed)())->missing('items.3.file_url')->missing('items.3.verified_by'));
    $response = $this->get(($this->signed)())->assertOk()->assertDownload('SMILE-document.pdf')->assertHeader('X-Content-Type-Options', 'nosniff');
    expect($response->headers->get('Cache-Control'))->toContain('no-store', 'private');
    expect($response->streamedContent())->toBe("%PDF-1.4\nFictional PDF test content\n%%EOF");
    $this->assertDatabaseHas('activity_log', ['event' => 'document_uploaded', 'subject_id' => $this->learner->id, 'causer_id' => $this->teacher->id]);
    $this->assertDatabaseHas('activity_log', ['event' => 'document_downloaded', 'subject_id' => $this->learner->id, 'causer_id' => $this->teacher->id]);
    expect(json_encode(DB::table('activity_log')->get()))->not->toContain('Fictional', $path);
});

test('real MIME PNG bytes are accepted and served as a generic attachment', function () {
    $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=');
    $this->post($this->fileUrl, ['file' => ($this->realFile)('Fictional.png', $png)])->assertSessionHasNoErrors();
    expect($this->document->fresh()->file_url)->toEndWith('.png');
    expect($this->get(($this->signed)())->assertDownload('SMILE-document.png')->streamedContent())->toBe($png);
});

test('upload rejects missing oversized executable mismatched or invalid files', function (string $kind) {
    $file = match ($kind) {
        'missing' => null,
        'oversized' => UploadedFile::fake()->create('Fictional.pdf', config('document_uploads.max_kb') + 1, 'application/pdf'),
        'executable' => ($this->realFile)('Fictional.php', '<?php echo 1;'),
        'mismatch' => ($this->realFile)('Fictional.pdf', '<html>Fictional</html>'),
        'extension' => ($this->realFile)('Fictional.html', "%PDF-1.4\nFictional"),
    };
    $this->post($this->fileUrl, ['file' => $file])->assertSessionHasErrors('file');
    expect($this->document->fresh()->file_url)->toBeNull();
    expect(Storage::disk('learner_documents')->allFiles())->toBe([]);
})->with(['missing', 'oversized', 'executable', 'mismatch', 'extension']);

test('upload cannot inject document ownership metadata or paths', function (string $field) {
    $this->post($this->fileUrl, ['file' => ($this->file)(), $field => 'untrusted'])->assertSessionHasErrors($field);
    expect($this->document->fresh()->file_url)->toBeNull();
})->with(['school_id', 'learner_id', 'document_type_id', 'file_url', 'verified_by', 'status', 'submitted_on', 'notes']);

test('attachment replacement is rejected and checklist corrections preserve existing file bytes', function () {
    $this->post($this->fileUrl, ['file' => ($this->file)()])->assertSessionHasNoErrors();
    $path = $this->document->fresh()->file_url;
    $this->post($this->fileUrl, ['file' => ($this->file)()])->assertSessionHasErrors(['file' => __('documents.already_attached')]);
    expect(Storage::disk('learner_documents')->allFiles())->toBe([$path]);
    $this->put($this->docUrl.'/4', ['status' => 'missing', 'notes' => null])->assertSessionHasNoErrors();
    expect($this->document->fresh()->file_url)->toBe($path);
    $this->get(($this->signed)())->assertOk();
});

test('a paper submission must be recorded before attaching a file', function () {
    $this->put($this->docUrl.'/4', ['status' => 'missing'])->assertSessionHasNoErrors();
    $this->post($this->fileUrl, ['file' => ($this->file)()])->assertSessionHasErrors(['file' => __('documents.record_submission')]);
    expect(Storage::disk('learner_documents')->allFiles())->toBe([]);
});

test('download rejects unsigned expired modified or another users links', function (string $kind) {
    $this->post($this->fileUrl, ['file' => ($this->file)()])->assertSessionHasNoErrors();
    $link = match ($kind) {
        'unsigned' => $this->fileUrl,
        'expired' => ($this->signed)(now()->subMinute()),
        'tampered' => ($this->signed)().'&extra=1',
        'wrong_user' => ($this->signed)(null, User::factory()->create(['school_id' => $this->teacher->school_id])->id),
    };
    $this->get($link)->assertForbidden();
    expect(DB::table('activity_log')->where('event', 'document_downloaded')->count())->toBe(0);
})->with(['unsigned', 'expired', 'tampered', 'wrong_user']);

test('other advisers schools and unsupported roles cannot upload or download even with a signed link', function (string $roles, bool $sameSchool) {
    $this->post($this->fileUrl, ['file' => ($this->file)()])->assertSessionHasNoErrors();
    $other = User::factory()->create($sameSchool ? ['school_id' => $this->teacher->school_id] : []);
    $other->assignRole(explode(',', $roles));
    $signed = ($this->signed)(null, $other->id);
    $this->actingAs($other)->post($this->fileUrl, ['file' => ($this->file)()])->assertNotFound();
    $this->get($signed)->assertNotFound();
    expect(LearnerDocument::query()->count())->toBe(0);
    expect(DB::table('activity_log')->where('event', 'document_uploaded')->count())->toBe(1);
    expect(DB::table('activity_log')->where('event', 'document_downloaded')->count())->toBe(0);
})->with([['sped_teacher,school_admin', true], ['sped_teacher,school_admin', false], ['parent', true], ['regular_teacher', true], ['school_admin', true], ['system_admin,sped_teacher,school_admin', true]]);

test('nested file endpoints cannot be used through another owned learner', function () {
    $this->post('/learners', ['lrn_status' => 'pending', 'first_name' => 'Fictional second', 'last_name' => 'Example', 'birthdate' => '2016-01-01', 'section_id' => $this->section->id,
        'enrolled_on' => '2026-10-11', 'consent_confirmed' => true, 'is_transferee' => false])->assertSessionHasNoErrors();
    $second = Learner::query()->where('id', '!=', $this->learner->id)->firstOrFail();
    $this->post('/learners/'.$second->id.'/documents/files/'.$this->document->id, ['file' => ($this->file)()])->assertForbidden();
    $this->get(($this->signed)(null, null, $second->id))->assertNotFound();
    expect($this->document->fresh()->file_url)->toBeNull();
});

test('revoked adviser permission invalidates a previously signed download', function () {
    $this->post($this->fileUrl, ['file' => ($this->file)()])->assertSessionHasNoErrors();
    $link = ($this->signed)();
    $other = User::factory()->create(['school_id' => $this->teacher->school_id]);
    $other->assignRole('sped_teacher');
    DB::table('sections')->where('id', $this->section->id)->update(['adviser_id' => $other->id]);
    $this->get($link)->assertNotFound();
    $this->actingAs($other)->get($link)->assertForbidden();
    $this->get(($this->signed)(null, $other->id))->assertOk();
});

test('inactive accounts and guests cannot access file endpoints', function () {
    $link = ($this->signed)();
    $other = User::factory()->create(['school_id' => $this->teacher->school_id, 'is_active' => false]);
    $other->assignRole('sped_teacher');
    $this->actingAs($other)->get($link)->assertNotFound();
    $this->post($this->fileUrl, ['file' => ($this->file)()])->assertNotFound();
    auth()->logout();
    $this->get($link)->assertRedirect('/login');
    $this->post($this->fileUrl, ['file' => ($this->file)()])->assertRedirect('/login');
});

test('upload audit failure rolls back the pointer and cleans up new bytes without logging private names', function () {
    Log::spy();
    $this->mock(AuditLearner::class)->shouldReceive('record')->once()->andThrow(new RuntimeException('Fictional private diagnosis'));
    $this->post($this->fileUrl, ['file' => ($this->file)()])->assertSessionHasErrors('file');
    expect($this->document->fresh()->file_url)->toBeNull();
    expect(Storage::disk('learner_documents')->allFiles())->toBe([]);
    Log::shouldHaveReceived('error')->with('Document upload failed', ['exception_type' => RuntimeException::class])->once();
});

test('download audit failure returns no bytes or private exception information', function () {
    $this->post($this->fileUrl, ['file' => ($this->file)()])->assertSessionHasNoErrors();
    Log::spy();
    $this->mock(AuditLearner::class)->shouldReceive('record')->once()->andThrow(new RuntimeException('Fictional private diagnosis'));
    $this->get(($this->signed)())->assertStatus(503)->assertDontSee('Fictional');
    Log::shouldHaveReceived('error')->with('Document download unavailable', ['exception_type' => RuntimeException::class])->once();
});

test('disabled auditing blocks both file upload and download', function () {
    $this->post($this->fileUrl, ['file' => ($this->file)()])->assertSessionHasNoErrors();
    $this->put($this->docUrl.'/1', $this->docData)->assertSessionHasNoErrors();
    $second = LearnerDocument::query()->where('document_type_id', 1)->firstOrFail();
    app(ActivityLogStatus::class)->disable();
    $this->get(($this->signed)())->assertStatus(503);
    $this->post($this->docUrl.'/files/'.$second->id, ['file' => ($this->file)()])->assertSessionHasErrors(['file' => __('documents.upload_failed')]);
    expect($second->fresh()->file_url)->toBeNull();
    expect(Storage::disk('learner_documents')->allFiles())->toHaveCount(1);
});

test('missing or out of learner storage pointers are never downloadable', function (string $kind) {
    if ($kind === 'missing_file') {
        $this->post($this->fileUrl, ['file' => ($this->file)()])->assertSessionHasNoErrors();
    }
    if ($kind === 'outside') {
        DB::table('learner_documents')->where('id', $this->document->id)->update(['file_url' => '../secret.pdf']);
    }
    if ($kind === 'missing_file') {
        Storage::disk('learner_documents')->delete($this->document->fresh()->file_url);
    }
    $this->get(($this->signed)())->assertNotFound();
    expect(DB::table('activity_log')->where('event', 'document_downloaded')->count())->toBe(0);
})->with(['no_attachment', 'missing_file', 'outside']);

test('storage write failure never saves an attachment pointer and reports only a generic error', function () {
    Log::spy();
    $disk = Mockery::mock(FilesystemAdapter::class);
    $disk->shouldReceive('putFileAs')->once()->andThrow(new RuntimeException('Fictional private file storage error'));
    $disk->shouldReceive('delete')->once()->andReturn(true);
    Storage::shouldReceive('disk')->with('learner_documents')->twice()->andReturn($disk);
    $this->post($this->fileUrl, ['file' => ($this->file)()])->assertSessionHasErrors(['file' => __('documents.upload_failed')]);
    expect($this->document->fresh()->file_url)->toBeNull();
    expect(DB::table('activity_log')->where('event', 'document_uploaded')->count())->toBe(0);
    Log::shouldHaveReceived('error')->with('Document upload failed', ['exception_type' => RuntimeException::class])->once();
});

test('storage read failure does not return a download or create a successful download audit', function () {
    $this->post($this->fileUrl, ['file' => ($this->file)()])->assertSessionHasNoErrors();
    Log::spy();
    $disk = Mockery::mock(FilesystemAdapter::class);
    $disk->shouldReceive('exists')->once()->andReturn(true);
    $disk->shouldReceive('readStream')->once()->andReturn(false);
    Storage::shouldReceive('disk')->with('learner_documents')->twice()->andReturn($disk);
    $this->get(($this->signed)())->assertStatus(503)->assertDontSee('Fictional');
    expect(DB::table('activity_log')->where('event', 'document_downloaded')->count())->toBe(0);
    Log::shouldHaveReceived('error')->with('Document download unavailable', ['exception_type' => RuntimeException::class])->once();
});
