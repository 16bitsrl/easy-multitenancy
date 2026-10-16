<?php

it('still registers central routes at their own uri without auto-prefixing', function () {
    $this->get('/pricing')->assertOk()->assertSee('pricing:none');
});
