<?php

namespace Tests\Feature;

use App\Enums\ScopeType;
use App\Enums\UserRole;
use App\Filament\Pages\SpeedMonitorPage;
use App\Models\User;
use App\Services\Omada\OmadaSpeedMonitor;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The speed monitor reads from the controller instead of the database, so the
 * only thing worth guarding is that the page still renders real numbers in
 * Mbps and that a controller that returns nothing does not break it.
 */
class SpeedMonitorPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $this->actingAs(User::factory()->create([
            'role' => UserRole::SuperAdmin,
            'scope_type' => ScopeType::All,
        ]));
    }

    public function test_it_shows_link_rates_in_mbps(): void
    {
        $this->fakeSnapshot($this->sampleSnapshot());

        Livewire::test(SpeedMonitorPage::class)
            ->assertOk()
            ->assertSee('TEST-SITE')
            ->assertSee('12.5')
            ->assertSee('Phone')
            ->assertSee('247.0');
    }

    public function test_it_renders_when_the_controller_returns_nothing(): void
    {
        $this->fakeSnapshot([]);

        Livewire::test(SpeedMonitorPage::class)
            ->assertOk()
            ->assertSee('No locations to show');
    }

    /**
     * @param  array<int, array<string, mixed>>  $snapshot
     */
    private function fakeSnapshot(array $snapshot): void
    {
        $this->app->instance(OmadaSpeedMonitor::class, new class($snapshot) extends OmadaSpeedMonitor
        {
            /** @param array<int, array<string, mixed>> $snapshot */
            public function __construct(private array $snapshot) {}

            public function snapshot(): array
            {
                return $this->snapshot;
            }

            public function refresh(): void {}
        });
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function sampleSnapshot(): array
    {
        return [[
            'site_code' => 'TEST-SITE',
            'site_name' => 'Test Site',
            'province' => 'Cagayan',
            'clients' => [[
                'mac' => 'AA:BB:CC:DD:EE:FF',
                'name' => 'Phone',
                'ssid' => 'DICT',
                'ap' => 'AP-1',
                'wireless' => true,
                'ap_to_client_kbps' => 12500,
                'client_to_ap_kbps' => 2500,
                'trafficDown' => 0,
                'trafficUp' => 0,
                'rssi' => -55,
                'signal' => 4,
                'guest' => true,
                'active' => true,
            ]],
            'client_count' => 1,
            'wireless_count' => 1,
            'wired_count' => 0,
            'ap_to_client_max_kbps' => 12500,
            'client_to_ap_max_kbps' => 2500,
            'low_rate_wireless_count' => 1,
            'error' => null,
        ], [
            'site_code' => 'BUSY-SITE',
            'site_name' => 'Busy Site',
            'province' => 'Cagayan',
            'clients' => [],
            'client_count' => 40,
            'wireless_count' => 40,
            'wired_count' => 0,
            'ap_to_client_max_kbps' => 247000,
            'client_to_ap_max_kbps' => 30000,
            'low_rate_wireless_count' => 0,
            'error' => null,
        ]];
    }
}
