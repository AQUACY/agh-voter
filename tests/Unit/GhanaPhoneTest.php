<?php

namespace Tests\Unit;

use App\Support\GhanaPhone;
use PHPUnit\Framework\TestCase;

class GhanaPhoneTest extends TestCase
{
    public function test_it_normalizes_and_masks_ghana_numbers(): void
    {
        $this->assertSame('233241231234', GhanaPhone::normalize('0241231234'));
        $this->assertSame('233241231234', GhanaPhone::normalize('+233241231234'));
        $this->assertSame('024****1234', GhanaPhone::mask('0241231234'));
    }
}
