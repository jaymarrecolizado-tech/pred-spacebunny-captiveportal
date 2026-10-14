<?php

namespace App\Services\Reporting;

use App\Enums\AttributionMethod;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Validated report criteria. Defaults mean "everything", which is why an empty
 * filter is always safe to render.
 */
final readonly class ReportFilters
{
    public function __construct(
        public ?CarbonInterface $from = null,
        public ?CarbonInterface $to = null,
        public ?int $supplierId = null,
        public ?string $province = null,
        public ?int $siteId = null,
        public ?AttributionMethod $attribution = null,
        public int $positiveThreshold = 4,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data, int $positiveThreshold): self
    {
        return new self(
            from: filled($data['from'] ?? null) ? Carbon::parse($data['from'])->startOfDay() : null,
            to: filled($data['to'] ?? null) ? Carbon::parse($data['to'])->endOfDay() : null,
            supplierId: filled($data['supplier_id'] ?? null) ? (int) $data['supplier_id'] : null,
            province: filled($data['province'] ?? null) ? (string) $data['province'] : null,
            siteId: filled($data['site_id'] ?? null) ? (int) $data['site_id'] : null,
            attribution: filled($data['attribution'] ?? null)
                ? AttributionMethod::tryFrom((string) $data['attribution'])
                : null,
            positiveThreshold: $positiveThreshold,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'from' => $this->from?->toDateString(),
            'to' => $this->to?->toDateString(),
            'supplier_id' => $this->supplierId,
            'province' => $this->province,
            'site_id' => $this->siteId,
            'attribution' => $this->attribution?->value,
        ];
    }
}
