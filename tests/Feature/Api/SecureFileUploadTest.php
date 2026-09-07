<?php

namespace Tests\Feature\Api;

use App\Models\Company;
use App\Models\PharmacyPrescription;
use App\Models\TenantApiKey;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Security contract for POST /api/tenant/uploads/prescription-doc — the
 * endpoint behind the SDUI file_picker on the New Prescription Intake form.
 */
class SecureFileUploadTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;
    protected string $token;

    protected function setUp(): void
    {
        parent::setUp();
        file_put_contents(storage_path('installed'), '{}');
        Storage::fake('public');

        $this->company = Company::create([
            'name' => 'Apex Health Pharmacy',
            'slug' => 'apex-health-upload',
            'status' => 'active',
            'pos_mode' => 'pharmacy',
            'currency' => 'USD',
            'currency_symbol' => '$',
        ]);

        $user = User::create([
            'company_id' => $this->company->id,
            'name' => 'Lead Pharmacist',
            'email' => 'rx-upload@apexhealth.test',
            'password' => Hash::make('secret123'),
            'role' => 'administrator',
            'status' => 'active',
        ]);

        $this->token = 'zk_live_'.bin2hex(random_bytes(16));
        TenantApiKey::create([
            'company_id' => $this->company->id,
            'user_id' => $user->id,
            'name' => 'Mobile POS Device',
            'token' => $this->token,
            'permissions' => ['*'],
        ]);
    }

    protected function tearDown(): void
    {
        @unlink(storage_path('installed'));
        parent::tearDown();
    }

    private function headers(): array
    {
        return ['Authorization' => 'Bearer '.$this->token, 'Accept' => 'application/json'];
    }

    private function upload(UploadedFile $file)
    {
        return $this->withHeaders($this->headers())
            ->post('/api/tenant/uploads/prescription-doc', ['file' => $file]);
    }

    public function test_it_accepts_an_image_and_returns_a_public_storage_url(): void
    {
        $res = $this->upload(UploadedFile::fake()->image('scan.jpg', 800, 1000));

        $res->assertOk()->assertJsonPath('success', true);
        $url = $res->json('url');
        $this->assertNotEmpty($url);
        $this->assertStringContainsString('tenant-uploads/'.$this->company->id.'/prescriptions/', $res->json('path'));

        // Stored under a UUID name — original filename never touches disk.
        $this->assertStringNotContainsString('scan.jpg', $res->json('path'));
        $this->assertSame('scan.jpg', $res->json('file_name'));
        Storage::disk('public')->assertExists($res->json('path'));
    }

    public function test_it_accepts_a_pdf_document(): void
    {
        $res = $this->upload(UploadedFile::fake()->create('prescription.pdf', 200, 'application/pdf'));

        $res->assertOk()->assertJsonPath('success', true);
        $this->assertStringEndsWith('.pdf', $res->json('path'));
    }

    public static function executablePayloads(): array
    {
        return [
            'shell script'   => ['payload.sh', 'text/x-shellscript'],
            'windows exe'    => ['tool.exe', 'application/x-dosexec'],
            'android apk'    => ['app.apk', 'application/vnd.android.package-archive'],
            'php script'     => ['shell.php', 'application/x-php'],
            'javascript'     => ['x.js', 'application/javascript'],
            'html'           => ['x.html', 'text/html'],
        ];
    }

    #[DataProvider('executablePayloads')]
    public function test_it_rejects_executable_and_script_uploads(string $name, string $mime): void
    {
        $res = $this->upload(UploadedFile::fake()->create($name, 50, $mime));

        $res->assertStatus(422)->assertJsonPath('success', false);
        $this->assertSame(0, PharmacyPrescription::query()->count());
        $this->assertEmpty(Storage::disk('public')->allFiles());
    }

    public function test_it_rejects_a_script_hidden_behind_a_double_extension(): void
    {
        $res = $this->upload(UploadedFile::fake()->create('rx.pdf.php', 50, 'application/pdf'));

        $res->assertStatus(422)->assertJsonPath('success', false);
        $this->assertEmpty(Storage::disk('public')->allFiles());
    }

    public function test_it_rejects_files_larger_than_10mb(): void
    {
        $res = $this->upload(UploadedFile::fake()->create('huge.pdf', 11 * 1024, 'application/pdf'));

        $res->assertStatus(422)->assertJsonPath('success', false);
    }

    public function test_it_requires_authentication(): void
    {
        $this->withHeader('Accept', 'application/json')
            ->post('/api/tenant/uploads/prescription-doc', [
                'file' => UploadedFile::fake()->image('scan.jpg'),
            ])
            ->assertStatus(401);
    }

    public function test_uploaded_url_is_persisted_with_the_prescription_record(): void
    {
        $uploaded = $this->upload(UploadedFile::fake()->image('rx.png'))->json('url');
        $this->assertNotEmpty($uploaded);

        $res = $this->withHeaders($this->headers())->postJson('/api/tenant/pharmacy/prescriptions', [
            'patient_name' => 'Sarah Connor',
            'doctor_name' => 'Dr. Gregory House',
            'prescription_date' => now()->toDateString(),
            'notes' => 'Amoxicillin 500mg TDS x 7 days',
            'rx_attachment_url' => $uploaded,
        ]);

        $res->assertOk()->assertJsonPath('success', true);
        $this->assertSame($uploaded, PharmacyPrescription::first()->rx_image_url);
    }
}
