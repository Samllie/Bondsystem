<?php

namespace Tests\Feature;

use App\Enums\CertificateType;
use App\Models\BondRequest;
use App\Models\CertificateVersion;
use App\Services\AmountToWordsService;
use App\Services\ConfirmationNumberService;
use App\Services\QRCodeGenerationService;
use App\Services\TemplateDataBuilder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CertificateVerificationTest extends TestCase
{
    use RefreshDatabase;

    private ConfirmationNumberService $confirmationNumberService;

    private QRCodeGenerationService $qrCodeGenerationService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->confirmationNumberService = new ConfirmationNumberService;
        $this->qrCodeGenerationService = new QRCodeGenerationService;
    }

    public function test_confirmation_number_is_generated_in_required_format(): void
    {
        $confirmationNumber = $this->confirmationNumberService->generate(
            CertificateType::BondCertificate,
            1,
            2026,
        );

        $this->assertMatchesRegularExpression(
            '/^SICI-BOND-2026-[A-F0-9]{8}-V1$/',
            $confirmationNumber,
        );
    }

    public function test_confirmation_number_is_unique(): void
    {
        $bondRequest = BondRequest::factory()->approved()->create([
            'certificate_type' => CertificateType::BondCertificate,
        ]);

        CertificateVersion::factory()->create([
            'bond_request_id' => $bondRequest->id,
            'version_number' => 1,
            'confirmation_number' => 'SICI-BOND-2026-AAAAAAAA-V1',
        ]);

        $confirmationNumber = $this->confirmationNumberService->generate(
            CertificateType::BondCertificate,
            1,
            2026,
        );

        $this->assertNotSame('SICI-BOND-2026-AAAAAAAA-V1', $confirmationNumber);
    }

    public function test_verification_token_is_generated(): void
    {
        $token = $this->confirmationNumberService->generateVerificationToken();

        $this->assertSame(64, strlen($token));
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $token);
    }

    public function test_verification_token_is_unique(): void
    {
        $existingToken = bin2hex(random_bytes(32));

        CertificateVersion::factory()->create([
            'verification_token' => $existingToken,
        ]);

        $token = $this->confirmationNumberService->generateVerificationToken();

        $this->assertNotSame($existingToken, $token);
    }

    public function test_qr_image_is_generated_and_stored(): void
    {
        if (! extension_loaded('gd')) {
            $this->markTestSkipped('GD extension is required to generate QR code images.');
        }

        $bondRequest = BondRequest::factory()->approved()->create([
            'certificate_type' => CertificateType::BondCertificate,
        ]);
        $token = $this->confirmationNumberService->generateVerificationToken();

        $relativePath = $this->qrCodeGenerationService->generateTemporary($bondRequest, 1, $token);
        $absolutePath = storage_path("app/{$relativePath}");

        $this->assertFileExists($absolutePath);
        $this->assertGreaterThan(0, filesize($absolutePath));
    }

    public function test_qr_code_path_is_finalized_with_version_id(): void
    {
        if (! extension_loaded('gd')) {
            $this->markTestSkipped('GD extension is required to generate QR code images.');
        }

        $version = CertificateVersion::factory()->create();
        $token = $this->confirmationNumberService->generateVerificationToken();
        $temporaryPath = $this->qrCodeGenerationService->generateTemporary(
            $version->bondRequest,
            $version->version_number,
            $token,
        );

        $finalPath = $this->qrCodeGenerationService->finalizeForVersion($version, $temporaryPath);

        $this->assertSame(
            sprintf('private/qr-codes/%s/%s/certificate_version_%d.png', now()->format('Y'), now()->format('m'), $version->id),
            $finalPath,
        );
        $this->assertFileExists(storage_path("app/{$finalPath}"));
    }

    public function test_confirmation_number_placeholder_is_text_and_qr_is_image(): void
    {
        $builder = new TemplateDataBuilder(new AmountToWordsService);
        $bondRequest = BondRequest::factory()->approved()->create([
            'certificate_type' => CertificateType::BondCertificate,
        ]);
        $bondRequest->load(['principal', 'signatory', 'notary', 'creator.branch']);

        $data = $builder->mergeVerificationPlaceholders(
            $builder->build($bondRequest),
            'SICI-BOND-2026-8F4A72C1-V1',
            [
                'path' => storage_path('app/test-qr.png'),
                'width' => 120,
                'height' => 120,
                'ratio' => true,
            ],
        );

        $this->assertSame('SICI-BOND-2026-8F4A72C1-V1', $data['text']['Confirmation Number']);
        $this->assertArrayHasKey('QR', $data['images']);
        $this->assertSame(120, $data['images']['QR']['width']);
    }

    public function test_valid_token_verification_page_shows_certificate_details(): void
    {
        $version = $this->certificateVersion(isCurrent: true);

        $response = $this->get(route('certificate-verification.show', [
            'verification_token' => $version->verification_token,
        ]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('CertificateVerification/Show')
            ->where('valid', true)
            ->where('status', 'CURRENT')
            ->where('confirmationNumber', $version->confirmation_number)
            ->where('versionNumber', $version->version_number)
        );
    }

    public function test_invalid_token_verification_page_shows_invalid_message(): void
    {
        $response = $this->get(route('certificate-verification.show', [
            'verification_token' => str_repeat('a', 64),
        ]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('CertificateVerification/Show')
            ->where('valid', false)
        );
    }

    public function test_successful_verification_increments_count_and_updates_last_verified_at(): void
    {
        $version = $this->certificateVersion(isCurrent: true, verificationCount: 2);

        $this->get(route('certificate-verification.show', [
            'verification_token' => $version->verification_token,
        ]))->assertOk();

        $version->refresh();

        $this->assertSame(3, $version->verification_count);
        $this->assertNotNull($version->last_verified_at);
    }

    public function test_archived_version_shows_archived_status_and_current_version_number(): void
    {
        $bondRequest = BondRequest::factory()->approved()->create([
            'certificate_type' => CertificateType::BondCertificate,
        ]);

        $archived = CertificateVersion::factory()->notCurrent()->create([
            'bond_request_id' => $bondRequest->id,
            'version_number' => 1,
            'confirmation_number' => 'SICI-BOND-2026-11111111-V1',
            'verification_token' => bin2hex(random_bytes(32)),
        ]);

        CertificateVersion::factory()->current()->create([
            'bond_request_id' => $bondRequest->id,
            'version_number' => 2,
            'confirmation_number' => 'SICI-BOND-2026-22222222-V2',
            'verification_token' => bin2hex(random_bytes(32)),
        ]);

        $response = $this->get(route('certificate-verification.show', [
            'verification_token' => $archived->verification_token,
        ]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('valid', true)
            ->where('status', 'ARCHIVED')
            ->where('currentVersionNumber', 2)
        );
    }

    public function test_current_version_shows_current_status(): void
    {
        $version = $this->certificateVersion(isCurrent: true);

        $response = $this->get(route('certificate-verification.show', [
            'verification_token' => $version->verification_token,
        ]));

        $response->assertInertia(fn ($page) => $page->where('status', 'CURRENT'));
    }

    public function test_confirmation_number_search_redirects_to_token_verification_page(): void
    {
        $version = $this->certificateVersion(isCurrent: true);

        $response = $this->post(route('certificate-verification.lookup'), [
            'confirmation_number' => $version->confirmation_number,
        ]);

        $response->assertRedirect(route('certificate-verification.show', [
            'verification_token' => $version->verification_token,
        ]));
    }

    public function test_confirmation_number_search_accepts_eight_character_hex_segment(): void
    {
        $version = CertificateVersion::factory()->create([
            'bond_request_id' => BondRequest::factory()->approved()->create()->id,
            'confirmation_number' => 'SICI-BOND-2026-67F3CB62-V1',
            'verification_token' => bin2hex(random_bytes(32)),
            'is_current' => true,
        ]);

        $response = $this->post(route('certificate-verification.lookup'), [
            'confirmation_number' => '67f3cb62',
        ]);

        $response->assertRedirect(route('certificate-verification.show', [
            'verification_token' => $version->verification_token,
        ]));
    }

    public function test_confirmation_number_search_rejects_ambiguous_partial_input(): void
    {
        CertificateVersion::factory()->create([
            'bond_request_id' => BondRequest::factory()->approved()->create()->id,
            'confirmation_number' => 'SICI-BOND-2026-67F3CB62-V1',
            'verification_token' => bin2hex(random_bytes(32)),
            'is_current' => true,
        ]);

        $this->post(route('certificate-verification.lookup'), [
            'confirmation_number' => 'SICI-BOND',
        ])->assertRedirect(route('certificate-verification.search'))
            ->assertSessionHasErrors('confirmation_number');
    }

    public function test_confirmation_number_search_normalizes_spaces_and_dashes(): void
    {
        $version = CertificateVersion::factory()->create([
            'bond_request_id' => BondRequest::factory()->approved()->create()->id,
            'confirmation_number' => 'SICI-BOND-2026-67F3CB62-V1',
            'verification_token' => bin2hex(random_bytes(32)),
            'is_current' => true,
        ]);

        $response = $this->post(route('certificate-verification.lookup'), [
            'confirmation_number' => 'SICI BOND 2026 67F3CB62 V1',
        ]);

        $response->assertRedirect(route('certificate-verification.show', [
            'verification_token' => $version->verification_token,
        ]));
    }

    public function test_audit_log_is_created_for_successful_verification(): void
    {
        $version = $this->certificateVersion(isCurrent: true);

        $this->get(route('certificate-verification.show', [
            'verification_token' => $version->verification_token,
        ]))->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'certificate_verified',
            'entity_type' => 'CertificateVersion',
            'entity_id' => $version->id,
            'user_id' => null,
        ]);
    }

    public function test_audit_log_is_created_for_failed_verification(): void
    {
        $this->get(route('certificate-verification.show', [
            'verification_token' => str_repeat('b', 64),
        ]))->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'certificate_verification_failed',
            'entity_type' => 'CertificateVersion',
            'user_id' => null,
        ]);
    }

    public function test_audit_log_is_created_for_confirmation_number_search(): void
    {
        $version = $this->certificateVersion(isCurrent: true);

        $this->post(route('certificate-verification.lookup'), [
            'confirmation_number' => $version->confirmation_number,
        ])->assertRedirect();

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'confirmation_number_searched',
            'entity_type' => 'CertificateVersion',
            'user_id' => null,
        ]);
    }

    public function test_car_confirmation_number_uses_car_type_code(): void
    {
        $confirmationNumber = $this->confirmationNumberService->generate(
            CertificateType::CarCertificate,
            1,
            2026,
        );

        $this->assertStringStartsWith('SICI-CAR-2026-', $confirmationNumber);
    }

    public function test_public_verification_lookup_is_rate_limited(): void
    {
        for ($attempt = 0; $attempt < 20; $attempt++) {
            $this->post(route('certificate-verification.lookup'), [
                'confirmation_number' => 'SICI-BOND-2026-NOTFOUND-V1',
            ])->assertRedirect();
        }

        $this->post(route('certificate-verification.lookup'), [
            'confirmation_number' => 'SICI-BOND-2026-NOTFOUND-V1',
        ])->assertStatus(429);
    }

    public function test_public_verification_show_is_rate_limited(): void
    {
        $token = str_repeat('a', 64);

        for ($attempt = 0; $attempt < 60; $attempt++) {
            $this->get(route('certificate-verification.show', [
                'verification_token' => $token,
            ]))->assertOk();
        }

        $this->get(route('certificate-verification.show', [
            'verification_token' => $token,
        ]))->assertStatus(429);
    }

    private function certificateVersion(bool $isCurrent = true, int $verificationCount = 0): CertificateVersion
    {
        $bondRequest = BondRequest::factory()->approved()->create([
            'certificate_type' => CertificateType::BondCertificate,
            'obligee_name' => 'Department of Public Works',
            'principal_name' => 'Sample Principal Inc.',
            'amount' => 1500000,
            'date_issued' => now()->subDay(),
            'expiry_date' => now()->addYear(),
        ]);

        return CertificateVersion::factory()->create([
            'bond_request_id' => $bondRequest->id,
            'version_number' => 1,
            'confirmation_number' => 'SICI-BOND-2026-'.Str::upper(bin2hex(random_bytes(4))).'-V1',
            'verification_token' => bin2hex(random_bytes(32)),
            'is_current' => $isCurrent,
            'verification_count' => $verificationCount,
        ]);
    }
}
