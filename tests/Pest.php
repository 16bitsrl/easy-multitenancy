<?php

use Bit16\EasyMultitenancy\Tests\CentralTestCase;
use Bit16\EasyMultitenancy\Tests\RootRouteTestCase;
use Bit16\EasyMultitenancy\Tests\TestCase;

uses(CentralTestCase::class)->in(__DIR__.'/Central');
uses(RootRouteTestCase::class)->in(__DIR__.'/RootRoute');
uses(TestCase::class)->in(__DIR__.'/Feature');
