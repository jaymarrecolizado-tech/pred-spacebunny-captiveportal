<?php

namespace App\Console\Commands;

use App\Services\Omada\ImportSitesFromOmada;
use App\Services\Omada\OmadaOpenApiClient;
use Illuminate\Console\Command;

class ImportOmadaSites extends Command
{
    protected $signature = 'sites:import-omada
                            {--dry-run : Show what would change without writing}';

    protected $description = 'Pull the site list from the Omada controller into the registry (read-only on the controller)';

    public function handle(OmadaOpenApiClient $client): int
    {
        if (! $client->isConfigured()) {
            $this->error('Omada Open API is not configured. Set OMADA_OPENAPI_* in .env.');

            return self::FAILURE;
        }

        $this->info('Asking the controller for its site list...');

        try {
            $sites = $client->sites();
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf('Controller reported %d site(s).', count($sites)));

        if ($this->option('dry-run')) {
            foreach (array_slice($sites, 0, 20) as $row) {
                $this->line('  '.$row['name'].'  ('.substr((string) $row['siteId'], 0, 10).'...)');
            }

            if (count($sites) > 20) {
                $this->line('  ... and '.(count($sites) - 20).' more');
            }

            $this->comment('Dry run: nothing was written.');

            return self::SUCCESS;
        }

        $result = app(ImportSitesFromOmada::class)->run();

        $this->info(sprintf(
            'Done. %d total, %d linked to existing registry entries, %d new, %d skipped.',
            $result['total'],
            $result['linked'],
            $result['created'],
            $result['skipped'],
        ));

        $this->comment('Every imported location is switched OFF. Switch on only the ones you want surveying.');

        return self::SUCCESS;
    }
}
