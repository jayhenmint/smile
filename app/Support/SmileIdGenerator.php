<?php

namespace App\Support;

class SmileIdGenerator
{
    public function generate(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $suffix = '';

        for ($position = 0; $position < 8; $position++) {
            $suffix .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return 'SM-'.$suffix;
    }
}
