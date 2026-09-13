<?php

namespace Tests\Unit;

use App\Services\WineNormalizationService;
use PHPUnit\Framework\TestCase;

class WineNormalizationServiceTest extends TestCase
{
    public function test_it_normalizes_accents_spacing_and_case(): void
    {
        $service = new WineNormalizationService;

        $this->assertSame('chateau figeac', $service->normalize('  Château   Figeac  '));
    }

    public function test_it_extracts_vintage(): void
    {
        $service = new WineNormalizationService;

        $this->assertSame(2021, $service->vintage('Grand Cru 2021'));
        $this->assertNull($service->vintage('NV'));
    }
}
