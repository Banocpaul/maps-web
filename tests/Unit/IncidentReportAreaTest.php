<?php

namespace Tests\Unit;

use App\Services\IncidentReportArea;
use Tests\TestCase;

class IncidentReportAreaTest extends TestCase
{
    public function test_location_is_checked_against_city_polygons(): void
    {
        $area = new IncidentReportArea;
        $this->assertTrue($area->contains(14.5794, 121.0359));
        $this->assertFalse($area->contains(10, 120));
        $this->assertFalse($area->contains(14.6, 121.0));
    }

    public function test_line_length_is_derived_from_coordinates(): void
    {
        $area = new IncidentReportArea;
        $this->assertSame(0.0, $area->lineLength([[121.0359,14.5794],[121.0359,14.5794]]));
        $this->assertGreaterThan(100, $area->lineLength([[121.0359,14.5794],[121.0370,14.5800]]));
        $this->assertLessThan(200, $area->lineLength([[121.0359,14.5794],[121.0370,14.5800]]));
    }
}
