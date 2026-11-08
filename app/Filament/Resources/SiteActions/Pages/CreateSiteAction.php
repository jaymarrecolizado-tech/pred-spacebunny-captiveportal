<?php

namespace App\Filament\Resources\SiteActions\Pages;

use App\Enums\ActionStatus;
use App\Filament\Resources\SiteActions\SiteActionResource;
use App\Models\Site;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateSiteAction extends CreateRecord
{
    protected static string $resource = SiteActionResource::class;

    public function mount(): void
    {
        parent::mount();

        $siteId = (int) request()->query('site_id', 0);

        if ($siteId <= 0 || ! Site::visibleTo(Auth::user())->whereKey($siteId)->exists()) {
            return;
        }

        $this->form->fill([
            'site_id' => $siteId,
            'status' => ActionStatus::Open->value,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $this->stampStatus($data);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function stampStatus(array $data): array
    {
        $status = $data['status'] ?? ActionStatus::Open->value;

        if (in_array($status, [ActionStatus::Resolved->value, ActionStatus::Verified->value], true) && blank($data['resolved_at'] ?? null)) {
            $data['resolved_at'] = now();
        }

        if ($status === ActionStatus::Verified->value && blank($data['verified_at'] ?? null)) {
            $data['verified_at'] = now();
        }

        return $data;
    }
}
