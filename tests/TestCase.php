<?php

namespace Tests;

use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use LazilyRefreshDatabase;

    /**
     * Fija el reloj dentro del horario de atención para que la emisión de
     * tickets no dependa de la hora real a la que se ejecute la suite.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(now()->setTime(10, 0));
    }
}
