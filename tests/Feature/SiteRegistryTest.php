<?php

namespace Tests\Feature;

use App\Enums\AttributionMethod;
use App\Enums\IntegrationMode;
use App\Enums\RecordStatus;
use App\Models\Site;
use App\Models\Supplier;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

/**
 * FR-SITE-002, FR-SITE-006, FR-SITE-007.
 */
class SiteRegistryTest extends TestCase
{
    use RefreshDatabase;

    public function test_site_codes_must_be_unique(): void
    {
        Supplier::factory()->create();
        Site::factory()->create(['site_code' => 'SITE-0001']);

        $this->expectException(QueryException::class);

        Site::factory()->create(['site_code' => 'SITE-0001']);
    }

    public function test_site_code_is_immutable_once_registered(): void
    {
        $site = Site::factory()->create(['site_code' => 'SITE-0002']);

        $this->expectException(LogicException::class);

        $site->update(['site_code' => 'SITE-9999']);
    }

    public function test_a_site_can_be_disabled_without_losing_history(): void
    {
        $site = Site::factory()->create();
        $site->update(['status' => RecordStatus::Inactive]);

        $this->assertSame(0, $site->responses()->count());
        $this->assertTrue($site->fresh()->trashed() === false);
    }

    public function test_public_attribution_follows_the_registered_integration_mode(): void
    {
        $qr = Site::factory()->create(['integration_mode' => IntegrationMode::QrCode]);
        $link = Site::factory()->create(['integration_mode' => IntegrationMode::SiteLink]);
        $portal = Site::factory()->create(['integration_mode' => IntegrationMode::CaptivePortal]);

        $this->assertSame(AttributionMethod::QrCode, $qr->publicAttributionMethod());
        $this->assertSame(AttributionMethod::SiteLink, $link->publicAttributionMethod());

        // A portal site must not claim verified attribution before a validated
        // gateway handoff exists (FR-RESP-006).
        $this->assertFalse($portal->publicAttributionMethod()->isVerified());
    }
}
