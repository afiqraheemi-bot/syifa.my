<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\WebsiteBuilder;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

final class PublicDoctorSectionRenderingTest extends TestCase
{
    public function test_doctor_section_renders_supporting_copy_and_accessible_fallback_profile(): void
    {
        $section = (object) [
            'doctors' => [
                (object) [
                    'name' => 'Dr. Aisyah Rahman',
                    'professionalTitle' => 'Pegawai Perubatan',
                    'photoAssetId' => null,
                ],
            ],
        ];

        $html = Blade::render(
            '<x-public.doctors :section="$section" :asset-urls="[]" :asset-dimensions="[]" />',
            ['section' => $section],
        );

        self::assertStringContainsString('Meet our doctors', $html);
        self::assertStringContainsString('dedicated to your care and wellbeing', $html);
        self::assertStringContainsString('doctor-card__fallback', $html);
        self::assertStringContainsString('aria-hidden="true">D</div>', $html);
        self::assertStringContainsString('doctor-card__title', $html);
        self::assertStringContainsString('Pegawai Perubatan', $html);
    }
}
