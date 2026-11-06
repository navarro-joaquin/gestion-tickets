<?php

namespace Tests;

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class ConcurrencyTestCase extends BaseTestCase
{
    use DatabaseMigrations;
}
