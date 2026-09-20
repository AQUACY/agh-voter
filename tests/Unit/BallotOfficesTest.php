<?php

namespace Tests\Unit;

use App\Support\BallotOffices;
use PHPUnit\Framework\TestCase;

class BallotOfficesTest extends TestCase
{
    public function test_vetted_offices_are_complete(): void
    {
        $offices = BallotOffices::all();

        $this->assertSame(['Godwin Boadi'], $offices['Chairperson']);
        $this->assertCount(3, $offices['Vice Chairperson']);
        $this->assertSame(['Abigail Esinam Tamakloe'], $offices['Secretary']);
        $this->assertCount(3, $offices['Financial Secretary']);
        $this->assertCount(2, $offices['Organizer']);
        $this->assertCount(2, $offices['Deputy Organizer']);
        $this->assertCount(6, $offices);
    }
}
