<?php

namespace Tests;

use App\Services\ActiveYear;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function withActiveYear(int $year): static
    {
        $this->withSession([ActiveYear::SESSION_KEY => $year]);

        return $this;
    }
}
