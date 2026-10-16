<?php

namespace Bit16\EasyMultitenancy\Tests;

class NoAutoPrefixTestCase extends TestCase
{
    public function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);

        config()->set('easy-multitenancy.routes.auto_prefix', false);
    }
}
