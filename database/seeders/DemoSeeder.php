<?php

namespace Database\Seeders;

use App\Enums\AssignmentPolicy;
use App\Enums\IntegrationMode;
use App\Enums\IntegrationState;
use App\Enums\IntegrationStatus;
use App\Enums\RecordStatus;
use App\Enums\ScopeType;
use App\Enums\SurveyStatus;
use App\Enums\SurveyVersionStatus;
use App\Enums\UserRole;
use App\Models\Integration;
use App\Models\Site;
use App\Models\Supplier;
use App\Models\Survey;
use App\Models\SurveyAssignment;
use App\Models\SurveyVersion;
use App\Models\User;
use App\Services\Surveys\DemographicQuestions;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

/**
 * Sample data only — fake sites and a fake operator account, no real DICT data
 * (Plan section 7.1).
 *
 * This seeder creates accounts with a known password (`password`), so it must
 * never reach a deployed environment. The guard lives here, not only in
 * DatabaseSeeder, so a direct `--class=DemoSeeder` invocation is refused too.
 * It throws rather than skipping quietly: a silent skip would let a deploy look
 * successful while producing a half-configured platform.
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local', 'testing')) {
            throw new RuntimeException(
                'Refusing to seed demo data in the "'.app()->environment().'" environment. '
                .'DemoSeeder creates known-password accounts and is local-only.'
            );
        }

        $admin = User::updateOrCreate(
            ['email' => 'admin@example.test'],
            [
                'name' => 'Demo DICT Administrator',
                'password' => Hash::make('password'),
                'role' => UserRole::SuperAdmin,
                'scope_type' => ScopeType::All,
                'is_active' => true,
            ],
        );

        $supplierA = Supplier::create([
            'code' => 'SUP-ALPHA',
            'name' => 'Alpha Telecom Services',
            'status' => RecordStatus::Active,
            'technical_contact_name' => 'Demo Contact A',
            'technical_contact_email' => 'alpha@example.test',
        ]);

        $supplierB = Supplier::create([
            'code' => 'SUP-BETA',
            'name' => 'Beta Networks',
            'status' => RecordStatus::Active,
        ]);

        // A supplier administrator proves row-level scope is real, not cosmetic.
        User::updateOrCreate(
            ['email' => 'supplier@example.test'],
            [
                'name' => 'Demo Supplier Administrator',
                'password' => Hash::make('password'),
                'role' => UserRole::SupplierAdmin,
                'scope_type' => ScopeType::Supplier,
                'scope_supplier_id' => $supplierA->id,
                'is_active' => true,
            ],
        );

        $siteA = Site::create([
            'site_code' => 'DEMO-0001',
            'name' => 'Plaza Feedback Site',
            'region' => 'Region I',
            'province' => 'Ilocos Norte',
            'municipality' => 'Laoag',
            'address_or_landmark' => 'Plaza entrance',
            'supplier_id' => $supplierA->id,
            'integration_mode' => IntegrationMode::QrCode,
            'integration_status' => IntegrationStatus::Active,
            'status' => RecordStatus::Active,
        ]);

        Site::create([
            'site_code' => 'DEMO-0002',
            'name' => 'Market Site',
            'region' => 'Region I',
            'province' => 'Ilocos Sur',
            'municipality' => 'Vigan',
            'supplier_id' => $supplierB->id,
            'integration_mode' => IntegrationMode::SiteLink,
            'integration_status' => IntegrationStatus::Configured,
            'status' => RecordStatus::Active,
        ]);

        $survey = Survey::create([
            'name' => 'Free Wi-Fi experience',
            'description' => 'Short anonymous survey about the free Wi-Fi service.',
            'status' => SurveyStatus::Active,
            'scope_type' => 'global',
        ]);

        $version = SurveyVersion::create([
            'survey_id' => $survey->id,
            'version_number' => 1,
            'schema_json' => [
                'questions' => [
                    [
                        'key' => 'privacy_notice',
                        'type' => 'info',
                        'label' => 'Your answers are anonymous. We do not ask for your name, phone number or email.',
                    ],
                    [
                        'key' => 'rating',
                        'type' => 'rating',
                        'label' => 'How is the Wi-Fi working today?',
                        'help' => '1 = not working, 5 = working very well.',
                        'min' => 1,
                        'max' => 5,
                        'required' => true,
                    ],
                    [
                        'key' => 'issue_type',
                        'type' => 'single_choice',
                        'label' => 'What is the main issue, if any?',
                        'required' => true,
                        'reports_as_issue_category' => true,
                        'options' => [
                            ['value' => 'none', 'label' => 'No problem'],
                            ['value' => 'slow', 'label' => 'Too slow'],
                            ['value' => 'drops', 'label' => 'Keeps dropping'],
                            ['value' => 'no_access', 'label' => 'Cannot connect'],
                        ],
                    ],
                    [
                        'key' => 'comment',
                        'type' => 'short_text',
                        'label' => 'Anything else you want to tell us?',
                        'help' => 'Optional.',
                        'max_length' => 500,
                    ],

                    // Gender and sector, in the same order and wording as the
                    // live survey on the server.
                    ...DemographicQuestions::questions(),
                ],
            ],
            'privacy_notice_version' => 'v1',
            'status' => SurveyVersionStatus::Published,
            'published_at' => now(),
            'published_by' => $admin->id,
        ]);

        $survey->update(['published_version_id' => $version->id]);

        SurveyAssignment::create([
            'survey_id' => $survey->id,
            'site_id' => null,
            'policy' => AssignmentPolicy::Optional,
            'status' => 'active',
        ]);

        // A test-mode integration so the gateway simulator works immediately.
        // Its secret is generated per seed run and never printed to a shared log.
        Integration::firstOrCreate(
            ['site_id' => $siteA->id, 'integration_type' => 'portal_handoff'],
            [
                'supplier_id' => $supplierA->id,
                'public_identifier' => Integration::generateIdentifier(),
                'secret' => Integration::generateSecret(),
                'status' => IntegrationState::Active,
                'is_test_mode' => true,
            ],
        );
    }
}
