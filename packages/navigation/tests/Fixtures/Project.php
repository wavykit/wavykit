<?php

declare(strict_types=1);

namespace WavyKit\Navigation\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;

/** Synthetic bound model; these fixtures perform no database operations. */
final class Project extends Model
{
    protected $guarded = [];
}
