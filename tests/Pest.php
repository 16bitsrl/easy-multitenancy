<?php

use Bit16\EasyMultitenancy\Tests\AppTestCase;
use Bit16\EasyMultitenancy\Tests\CentralTestCase;
use Bit16\EasyMultitenancy\Tests\CustomParameterTestCase;
use Bit16\EasyMultitenancy\Tests\NoAutoPrefixTestCase;
use Bit16\EasyMultitenancy\Tests\RootRouteTestCase;
use Bit16\EasyMultitenancy\Tests\TestCase;

uses(CentralTestCase::class)->in(__DIR__.'/Central');
uses(RootRouteTestCase::class)->in(__DIR__.'/RootRoute');
uses(CustomParameterTestCase::class)->in(__DIR__.'/CustomParameter');
uses(NoAutoPrefixTestCase::class)->in(__DIR__.'/NoAutoPrefix');
uses(AppTestCase::class)->in(__DIR__.'/Testing');
uses(TestCase::class)->in(__DIR__.'/Feature');
