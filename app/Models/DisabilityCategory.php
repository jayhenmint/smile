<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// Global, read-only reference catalogue from schema.sql; contains no learner data.
class DisabilityCategory extends Model
{
    public $timestamps = false;

    protected $guarded = ['*'];
}
