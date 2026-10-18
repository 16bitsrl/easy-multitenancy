<?php

namespace Bit16\EasyMultitenancy\Tests\Fixtures\Seeders;

use Bit16\EasyMultitenancy\Tests\Fixtures\Post;
use Illuminate\Database\Seeder;

class WelcomePostSeeder extends Seeder
{
    public function run(): void
    {
        Post::create(['title' => 'Welcome']);
    }
}
