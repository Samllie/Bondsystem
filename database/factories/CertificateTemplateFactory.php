<?php

namespace Database\Factories;

use App\Enums\CertificateTemplateType;
use App\Models\CertificateTemplate;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CertificateTemplate>
 */
class CertificateTemplateFactory extends Factory
{
    protected $model = CertificateTemplate::class;

    public function definition(): array
    {
        return [
            'template_type' => CertificateTemplateType::Bond,
            'template_name' => fake()->words(3, true),
            'version' => 1,
            'file_path' => 'certificate-templates/test_template.docx',
            'original_filename' => 'template.docx',
            'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'file_size' => 1024,
            'uploaded_by' => User::factory(),
            'is_active' => false,
            'archived_at' => null,
        ];
    }

    public function active(): static
    {
        return $this->state(fn () => [
            'is_active' => true,
            'archived_at' => null,
        ]);
    }

    public function archived(): static
    {
        return $this->state(fn () => [
            'is_active' => false,
            'archived_at' => now(),
        ]);
    }

    public function car(): static
    {
        return $this->state(fn () => [
            'template_type' => CertificateTemplateType::Car,
        ]);
    }
}
