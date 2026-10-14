<?php

namespace Tests\Unit;

use Tests\TestCase;

/**
 * The Omada grant duration must stay inside the range the controller can
 * represent. Kept as a test so it cannot drift back to microseconds by
 * accident — that bug cost a full diagnostic cycle in the pilot.
 */
class OmadaGrantDurationTest extends TestCase
{
    public function test_it_stays_32_bit_safe_so_the_grant_is_not_born_expired(): void
    {
        $duration = (int) config('portal.auth_duration_ms');

        $this->assertGreaterThan(0, $duration);
        $this->assertLessThanOrEqual(2147483647, $duration);
    }

    public function test_it_is_expressed_in_milliseconds_never_microseconds(): void
    {
        // 8 hours in microseconds (28,800,000,000) is what broke the pilot.
        $duration = (int) config('portal.auth_duration_ms');

        $this->assertLessThanOrEqual(3600000 * 24, $duration);
        $this->assertNotEquals(8 * 3600 * 1000000, $duration);
    }
}
