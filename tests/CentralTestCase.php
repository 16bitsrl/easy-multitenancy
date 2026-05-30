<?php

namespace Bit16\EasyMultitenancy\Tests;

class CentralTestCase extends TestCase
{
    public function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);

        config()->set('easy-multitenancy.central.enabled', true);
    }
}
