<?php

namespace App\Services\Omada;

use App\Models\Site;
use App\Models\Supplier;
use Illuminate\Support\Str;

/**
 * Brings the controller's site list into the registry.
 *
 * The controller is the source of truth for which locations exist; this app is
 * the source of truth for what DICT decided about them. So the import only ever
 * adds the controller's identity and fills blanks - it never overwrites a
 * site_code, a supplier, a status, a survey trigger, or anything else a person
 * set deliberately.
 *
 * Every imported site lands switched OFF (`never`). Turning surveying on is a
 * decision DICT makes per location, and a bulk import must never start
 * interrupting the public on its own.
 */
class ImportSitesFromOmada
{
    public function __construct(private readonly OmadaOpenApiClient $client) {}

    /**
     * @return array<string, int>
     */
    public function run(): array
    {
        $remote = $this->client->sites();

        $result = ['total' => count($remote), 'linked' => 0, 'created' => 0, 'skipped' => 0];

        // A site cannot exist without a supplier, and the controller does not
        // know who manages a location. Rather than guess, imported sites land on
        // a clearly named placeholder that shows up in the admin as work still
        // to do, instead of silently landing on a real supplier's books.
        $placeholderSupplier = $this->unassignedSupplier();

        foreach ($remote as $row) {
            $siteId = (string) ($row['siteId'] ?? '');
            $name = trim((string) ($row['name'] ?? ''));

            if ($siteId === '' || $name === '') {
                $result['skipped']++;

                continue;
            }

            $existing = $this->findMatch($siteId, $name);

            if ($existing !== null) {
                // Link only. Nothing already decided is touched.
                if ($existing->omada_site_id === $siteId) {
                    $result['linked']++;
                } else {
                    $existing->forceFill(['omada_site_id' => $siteId])->save();
                    $result['linked']++;
                }

                continue;
            }

            Site::create([
                'omada_site_id' => $siteId,
                // Unique and stable: the registry identity must never be reused
                // by a different location later.
                'site_code' => $this->uniqueCode($name, $siteId),
                'name' => Str::limit($name, 255, ''),
                'province' => $this->provinceFromName($name),
                'supplier_id' => $placeholderSupplier->id,
                // No gateway integration mode is claimed: these locations exist
                // on the controller but none of them are confirmed to redirect
                // to this platform yet.
                'integration_mode' => 'qr_code',
                'integration_status' => 'draft',
                'status' => 'active',
                // Imported, never switched on.
                'survey_trigger_mode' => 'never',
                'survey_trigger_days' => 7,
            ]);

            $result['created']++;
        }

        return $result;
    }

    /**
     * Find or create the placeholder supplier imported sites are parked against.
     */
    private function unassignedSupplier(): Supplier
    {
        return Supplier::firstOrCreate(
            ['code' => 'UNASSIGNED'],
            [
                'name' => 'Unassigned (needs supplier)',
                'status' => 'active',
                'notes' => 'Locations imported from the Omada controller. Assign the real supplier before treating these sites as live.',
            ],
        );
    }

    /**
     * Match a controller site to a registry site.
     *
     * Exact omada_site_id wins. Otherwise the name is compared with spaces,
     * punctuation and case removed, because the pilot site was registered by
     * hand as "POP-TEST" while the controller calls it "POP Test".
     */
    private function findMatch(string $siteId, string $name): ?Site
    {
        $byId = Site::query()->where('omada_site_id', $siteId)->first();

        if ($byId !== null) {
            return $byId;
        }

        $normalised = $this->normalise($name);

        return Site::query()
            ->whereNull('omada_site_id')
            ->get()
            ->first(fn (Site $site) => $this->normalise($site->name) === $normalised
                || $this->normalise($site->site_code) === $normalised);
    }

    private function normalise(string $value): string
    {
        return (string) preg_replace('/[^a-z0-9]/', '', Str::lower($value));
    }

    /**
     * A site_code that is unique, readable and never collides. Falls back to a
     * slice of the controller id when the name yields nothing usable.
     */
    private function uniqueCode(string $name, string $siteId): string
    {
        $slug = Str::upper((string) preg_replace('/[^A-Za-z0-9]+/', '-', $name));
        $slug = trim((string) preg_replace('/-+/', '-', $slug), '-');
        $slug = Str::limit($slug, 48, '');

        if ($slug === '') {
            $slug = 'OMADA-'.Str::upper(Str::substr($siteId, 0, 8));
        }

        $code = $slug;
        $suffix = 2;

        while (Site::query()->where('site_code', $code)->exists()) {
            $code = Str::limit($slug, 48, '').'-'.$suffix;
            $suffix++;
        }

        return $code;
    }

    /**
     * "Alcala East Central School" has no province in the controller's payload,
     * but it reads better in a report than a blank, and the name is the only
     * hint available. Left empty rather than guessed where there is nothing to
     * work from.
     */
    private function provinceFromName(string $name): ?string
    {
        // Matches known Philippine provinces/cities inside a site name, e.g.
        // "Piat Municipal Hall" -> Piat, "CSU Laoag" -> Laoag.
        $known = [
            'Piat', 'Laoag', 'Candon', 'Vigan', 'Naguilian', 'Cagayan', 'Tuguegarao',
            'Ilagan', 'Santiago', 'Tabuk', 'Bantay', 'Agoo', 'Mankayan', 'Kabugao',
        ];

        foreach ($known as $place) {
            if (stripos($name, $place) !== false) {
                return $place;
            }
        }

        return null;
    }
}
