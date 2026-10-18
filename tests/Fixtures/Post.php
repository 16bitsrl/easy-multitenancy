<?php

namespace Bit16\EasyMultitenancy\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $title
 */
class Post extends Model
{
    public $timestamps = false;

    protected $guarded = [];
}
