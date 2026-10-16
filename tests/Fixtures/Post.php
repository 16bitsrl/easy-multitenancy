<?php

namespace Bit16\EasyMultitenancy\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;

class Post extends Model
{
    public $timestamps = false;

    protected $guarded = [];
}
