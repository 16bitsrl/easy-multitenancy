<?php

namespace Bit16\EasyMultitenancy\Tests;

class CustomParameterTestCase extends TestCase
{
    public function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);

        config()->set('easy-multitenancy.routes.parameter', 'workspace');
    }
}
