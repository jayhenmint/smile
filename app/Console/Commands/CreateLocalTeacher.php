<?php

namespace App\Console\Commands;

use App\Models\School;
use App\Models\Scopes\SchoolScope;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class CreateLocalTeacher extends Command
{
    protected $signature = 'smile:create-local-teacher {--school= : Development school name} {--name= : Teacher display name} {--email= : Login email}';

    protected $description = 'Bootstrap the first local development teacher with a generated password';

    public function handle(): int
    {
        if (! app()->environment('local')) {
            $this->error('This command is available only in the local environment.');

            return self::FAILURE;
        }

        $attributes = Validator::make([
            'school' => $this->option('school'),
            'name' => $this->option('name'),
            'email' => $this->option('email'),
        ], [
            'school' => ['required', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
        ]);

        if ($attributes->fails()) {
            $this->error('Provide a school name, teacher name, and valid email address.');

            return self::FAILURE;
        }

        // This trusted operator command is the sole bootstrap exception to account scoping.
        // It never resets or changes an existing account.
        if (User::withoutGlobalScope(SchoolScope::class)->exists()) {
            $this->error('Accounts already exist. Initial setup cannot be repeated.');

            return self::FAILURE;
        }

        $data = $attributes->validated();
        $password = bin2hex(random_bytes(16));

        DB::transaction(function () use ($data, $password) {
            (new RolesSeeder)->run();
            $school = School::create(['name' => $data['school']]);
            $teacher = new User([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $password,
            ]);
            $teacher->school_id = $school->id;
            $teacher->is_active = true;
            $teacher->save();
            $teacher->assignRole(['school_admin', 'sped_teacher']);
        });

        $this->info('Local teacher created with school_admin and sped_teacher roles.');
        $this->line('Password: '.$password);
        $this->comment('Save this password now; it is shown only once. Change it in Settings after login.');

        return self::SUCCESS;
    }
}
