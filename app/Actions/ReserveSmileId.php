<?php

namespace App\Actions;

use App\Support\SmileIdGenerator;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ReserveSmileId
{
    public function __construct(private readonly SmileIdGenerator $generator) {}

    /**
     * Internal identity allocation; call inside the learner-creation transaction.
     * The registry contains only IDs, not learner or school information.
     */
    public function reserve(): string
    {
        for ($attempt = 0; $attempt < 100; $attempt++) {
            $id = $this->generator->generate();

            // Let the global primary key arbitrate concurrent allocations.
            if (DB::table('smile_id_reservations')->insertOrIgnore(['smile_id' => $id]) === 1) {
                return $id;
            }
        }

        throw new RuntimeException('Unable to allocate a SMILE ID. Please try again.');
    }
}
