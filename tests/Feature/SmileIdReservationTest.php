<?php

use App\Actions\ReserveSmileId;
use App\Support\SmileIdGenerator;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

test('identity allocation persists an ID without learner or school data', function () {
    $id = app(ReserveSmileId::class)->reserve();

    expect($id)->toMatch('/^SM-[A-HJ-NP-Z2-9]{8}$/');
    $this->assertDatabaseHas('smile_id_reservations', ['smile_id' => $id]);
    expect((array) DB::table('smile_id_reservations')->sole())->toBe(['smile_id' => $id]);
});

test('a global unique constraint rejects a second reservation of the same ID', function () {
    $id = app(ReserveSmileId::class)->reserve();

    expect(fn () => DB::table('smile_id_reservations')->insert(['smile_id' => $id]))
        ->toThrow(QueryException::class);
});

test('allocation retries a database collision instead of returning a used ID', function () {
    DB::table('smile_id_reservations')->insert(['smile_id' => 'SM-ABCDEFGH']);
    $generator = Mockery::mock(SmileIdGenerator::class);
    $generator->shouldReceive('generate')->once()->ordered()->andReturn('SM-ABCDEFGH');
    $generator->shouldReceive('generate')->once()->ordered()->andReturn('SM-JKLMNPQR');

    $id = (new ReserveSmileId($generator))->reserve();

    expect($id)->toBe('SM-JKLMNPQR');
    $this->assertDatabaseCount('smile_id_reservations', 2);
});

test('collision retries stay usable inside a learner creation transaction', function () {
    DB::table('smile_id_reservations')->insert(['smile_id' => 'SM-ABCDEFGH']);
    $generator = Mockery::mock(SmileIdGenerator::class);
    $generator->shouldReceive('generate')->once()->ordered()->andReturn('SM-ABCDEFGH');
    $generator->shouldReceive('generate')->once()->ordered()->andReturn('SM-JKLMNPQR');

    $id = DB::transaction(fn () => (new ReserveSmileId($generator))->reserve());

    expect($id)->toBe('SM-JKLMNPQR');
    $this->assertDatabaseCount('smile_id_reservations', 2);
});

test('uncommitted reservations roll back when learner creation fails', function () {
    try {
        DB::transaction(function () {
            app(ReserveSmileId::class)->reserve();
            throw new RuntimeException('Creation failed.');
        });
    } catch (RuntimeException) {
        // Nothing was issued to a learner, so there is no identifier to retain.
    }

    $this->assertDatabaseCount('smile_id_reservations', 0);
});

test('persistent collisions fail without looping forever or changing reservations', function () {
    DB::table('smile_id_reservations')->insert(['smile_id' => 'SM-ABCDEFGH']);
    $generator = Mockery::mock(SmileIdGenerator::class);
    $generator->shouldReceive('generate')->times(100)->andReturn('SM-ABCDEFGH');

    expect(fn () => (new ReserveSmileId($generator))->reserve())
        ->toThrow(RuntimeException::class, 'Unable to allocate a SMILE ID. Please try again.');
    $this->assertDatabaseCount('smile_id_reservations', 1);
});

test('reserved identifiers cannot be changed or removed even by direct database writes', function (string $operation) {
    DB::table('smile_id_reservations')->insert(['smile_id' => 'SM-ABCDEFGH']);

    expect(fn () => DB::transaction(function () use ($operation) {
        $query = DB::table('smile_id_reservations')->where('smile_id', 'SM-ABCDEFGH');

        if ($operation === 'update') {
            $query->update(['smile_id' => 'SM-JKLMNPQR']);
        } else {
            $query->delete();
        }
    }))->toThrow(QueryException::class);

    $this->assertDatabaseHas('smile_id_reservations', ['smile_id' => 'SM-ABCDEFGH']);
})->with(['update', 'delete']);

test('PostgreSQL cannot truncate issued identifier history', function () {
    if (DB::getDriverName() !== 'pgsql') {
        $this->markTestSkipped('TRUNCATE is a PostgreSQL operation.');
    }

    $id = app(ReserveSmileId::class)->reserve();
    expect(fn () => DB::transaction(fn () => DB::statement('TRUNCATE smile_id_reservations')))
        ->toThrow(QueryException::class);
    $this->assertDatabaseHas('smile_id_reservations', ['smile_id' => $id]);
});

test('the database rejects malformed reserved identifiers', function (string $id) {
    expect(fn () => DB::table('smile_id_reservations')->insert(['smile_id' => $id]))
        ->toThrow(QueryException::class);
})->with(['SM-ABCDEFGI', 'SM-ABCDEFG0', 'SM-ABCDEFG1', 'SM-ABCDEFG', 'sm-ABCDEFGH', '001234567890']);

test('allocator propagates database failures rather than treating them as collisions', function () {
    DB::shouldReceive('table')->once()->with('smile_id_reservations')->andThrow(new RuntimeException('Database unavailable.'));

    expect(fn () => app(ReserveSmileId::class)->reserve())->toThrow(RuntimeException::class, 'Database unavailable.');
});
