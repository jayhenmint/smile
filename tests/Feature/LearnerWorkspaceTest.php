<?php

use App\Models\School;
use App\Models\SchoolYear;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
    $this->seed(RolesSeeder::class);
});

test('teachers can open the learner layout without receiving placeholder learner records', function (array $roles) {
    $teacher = User::factory()->create();
    $teacher->assignRole($roles);
    $this->actingAs($teacher)->get('/learners')->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('learners/index')
            ->where('workspace.canViewLearners', true)->where('workspace.schoolName', $teacher->school->name)
            ->missing('learners')->missing('learnerRecords')->missing('statistics'));
})->with([[['sped_teacher']], [['sped_teacher', 'school_admin']]]);

test('learner workspace denies other roles including a system admin with teacher roles', function (array $roles) {
    $user = User::factory()->create();
    $user->assignRole($roles);
    $this->actingAs($user)->get('/learners')->assertForbidden();
})->with([[['parent']], [['regular_teacher']], [['school_admin']], [['system_admin', 'sped_teacher', 'school_admin']]]);

test('learner workspace denies guests inactive teachers and teachers without a school', function () {
    $this->get('/learners')->assertRedirect('/login');
    $inactive = User::factory()->create(['is_active' => false]);
    $inactive->assignRole('sped_teacher');
    $this->actingAs($inactive)->get('/learners')->assertForbidden();
    $unassigned = User::factory()->create(['school_id' => null]);
    $unassigned->assignRole('sped_teacher');
    $this->actingAs($unassigned)->get('/learners')->assertForbidden();
});

test('workspace context cannot be switched to another school through request parameters', function () {
    $teacher = User::factory()->create();
    $teacher->assignRole('sped_teacher');
    $year = SchoolYear::factory()->create(['school_id' => $teacher->school_id, 'is_current' => true]);
    $other = School::factory()->create();
    SchoolYear::factory()->create(['school_id' => $other->id, 'label' => 'Other school year', 'is_current' => true]);

    $this->actingAs($teacher)->get('/learners?school_id='.$other->id)
        ->assertInertia(fn (Assert $page) => $page->where('workspace.schoolName', $teacher->school->name)
            ->where('workspace.schoolYear', $year->label));
});

test('dashboard shares null school year rather than guessing a calendar', function () {
    $teacher = User::factory()->create();
    $teacher->assignRole('sped_teacher');
    $this->actingAs($teacher)->get('/dashboard')->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('dashboard')
            ->where('workspace.schoolYear', null)->where('workspace.text.learners', 'Learners'));
});
