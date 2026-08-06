<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\WebsiteBuilder\Infrastructure\SyifaAi;

use App\Modules\WebsiteBuilder\Application\SyifaAi\Exceptions\SyifaAiNotReadyException;
use App\Modules\WebsiteBuilder\Application\SyifaAi\SyifaAiCapability;
use App\Modules\WebsiteBuilder\Application\SyifaAi\SyifaAiGenerationRequest;
use App\Modules\WebsiteBuilder\Application\SyifaAi\SyifaAiSection;
use App\Modules\WebsiteBuilder\Infrastructure\SyifaAi\OpenAiSyifaAiProvider;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class OpenAiSyifaAiProviderTest extends TestCase
{
    #[Test]
    public function it_uses_private_structured_responses_and_returns_governed_assistance(): void
    {
        config()->set('syifa_ai.enabled', true);
        config()->set('syifa_ai.api_key', 'test-secret');
        config()->set('syifa_ai.model', 'gpt-5.6-luna');
        config()->set('syifa_ai.base_url', 'https://api.openai.test/v1');
        Http::fake([
            'https://api.openai.test/v1/responses' => Http::response([
                'model' => 'gpt-5.6-luna-2026-08-01',
                'output' => [[
                    'type' => 'message',
                    'content' => [[
                        'type' => 'output_text',
                        'text' => json_encode([
                            'title' => 'Hero lebih jelas',
                            'summary' => 'Cadangan ini menggunakan fakta klinik yang tersedia.',
                            'suggestions' => [[
                                'field' => 'headline',
                                'label' => 'Headline',
                                'proposed_value' => 'Penjagaan kesihatan keluarga yang dipercayai',
                                'rationale' => 'Mesej lebih terus dan mudah difahami.',
                            ]],
                            'checks' => [[
                                'label' => 'Medical claims',
                                'status' => 'pass',
                                'message' => 'No unsupported claims were added.',
                            ]],
                            'next_actions' => ['Review the clinic facts before saving.'],
                        ], JSON_THROW_ON_ERROR),
                    ]],
                ]],
                'usage' => ['input_tokens' => 321, 'output_tokens' => 123],
            ]),
        ]);

        $result = (new OpenAiSyifaAiProvider)->generate(new SyifaAiGenerationRequest(
            SyifaAiCapability::ContentAssistant,
            SyifaAiSection::Hero,
            null,
            ['clinic' => ['name' => 'Klinik Afiq'], 'draft_sections' => []],
            'safe-user-hash',
        ));

        self::assertSame('Hero lebih jelas', $result->title);
        self::assertSame(321, $result->inputTokens);
        self::assertSame(123, $result->outputTokens);
        self::assertSame('headline', $result->suggestions[0]['field']);
        Http::assertSent(static function (Request $request): bool {
            $payload = $request->data();

            return $request->url() === 'https://api.openai.test/v1/responses'
                && $request->hasHeader('Authorization', 'Bearer test-secret')
                && $payload['store'] === false
                && $payload['safety_identifier'] === 'safe-user-hash'
                && $payload['text']['format']['type'] === 'json_schema'
                && $payload['text']['format']['strict'] === true;
        });
    }

    #[Test]
    public function it_fails_closed_when_provider_configuration_is_not_ready(): void
    {
        config()->set('syifa_ai.enabled', false);
        config()->set('syifa_ai.api_key', null);

        $this->expectException(SyifaAiNotReadyException::class);
        (new OpenAiSyifaAiProvider)->generate(new SyifaAiGenerationRequest(
            SyifaAiCapability::DesignerCopilot,
            null,
            null,
            [],
            'safe-user-hash',
        ));
    }
}
