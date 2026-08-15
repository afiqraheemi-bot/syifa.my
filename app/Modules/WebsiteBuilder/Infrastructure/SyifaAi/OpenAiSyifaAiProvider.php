<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Infrastructure\SyifaAi;

use App\Modules\WebsiteBuilder\Application\SyifaAi\Exceptions\SyifaAiNotReadyException;
use App\Modules\WebsiteBuilder\Application\SyifaAi\Exceptions\SyifaAiProviderException;
use App\Modules\WebsiteBuilder\Application\SyifaAi\SyifaAiCapability;
use App\Modules\WebsiteBuilder\Application\SyifaAi\SyifaAiGenerationRequest;
use App\Modules\WebsiteBuilder\Application\SyifaAi\SyifaAiGenerationResult;
use App\Modules\WebsiteBuilder\Contracts\SyifaAi\SyifaAiProviderInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use JsonException;

final readonly class OpenAiSyifaAiProvider implements SyifaAiProviderInterface
{
    public function isConfigured(): bool
    {
        $apiKey = config('syifa_ai.api_key');

        return config('syifa_ai.provider') === 'openai'
            && (bool) config('syifa_ai.enabled')
            && is_string($apiKey)
            && trim($apiKey) !== '';
    }

    public function generate(SyifaAiGenerationRequest $request): SyifaAiGenerationResult
    {
        if (! $this->isConfigured()) {
            throw new SyifaAiNotReadyException('SYIFA AI is not configured for this environment yet.');
        }

        $apiKey = (string) config('syifa_ai.api_key');
        $model = (string) config('syifa_ai.model', 'gpt-5.6-luna');
        try {
            $response = Http::baseUrl(rtrim((string) config('syifa_ai.base_url'), '/'))
                ->withToken($apiKey)
                ->acceptJson()
                ->asJson()
                ->connectTimeout(5)
                ->timeout(max(5, (int) config('syifa_ai.timeout_seconds', 30)))
                ->post('/responses', [
                    'model' => $model,
                    'store' => false,
                    'safety_identifier' => $request->safetyIdentifier,
                    'max_output_tokens' => max(400, (int) config('syifa_ai.max_output_tokens', 1400)),
                    'reasoning' => ['effort' => 'low'],
                    'input' => [
                        ['role' => 'system', 'content' => [['type' => 'input_text', 'text' => $this->systemPrompt($request)]]],
                        ['role' => 'user', 'content' => [['type' => 'input_text', 'text' => $this->userPrompt($request)]]],
                    ],
                    'text' => ['format' => [
                        'type' => 'json_schema',
                        'name' => 'syifa_ai_assistance',
                        'strict' => true,
                        'schema' => $this->schema(),
                    ]],
                ]);
        } catch (ConnectionException) {
            throw new SyifaAiProviderException('SYIFA AI could not be reached. Please try again.');
        }

        if (! $response->successful()) {
            throw new SyifaAiProviderException('SYIFA AI could not complete the request. Please try again.');
        }

        $body = $response->json();
        if (! is_array($body)) {
            throw new SyifaAiProviderException('SYIFA AI returned an invalid response.');
        }
        $text = $this->outputText($body);
        try {
            $content = json_decode($text, true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new SyifaAiProviderException('SYIFA AI returned an invalid response.');
        }
        if (! is_array($content)) {
            throw new SyifaAiProviderException('SYIFA AI returned an invalid response.');
        }

        return $this->result($content, $body, $model);
    }

    private function systemPrompt(SyifaAiGenerationRequest $request): string
    {
        $mode = match ($request->capability) {
            SyifaAiCapability::ContentAssistant => 'Suggest improved Malay website copy for only the requested section.',
            SyifaAiCapability::QualityReview => 'Audit SEO, clarity, completeness, accessibility and content quality. Prefer checks over rewrites.',
            SyifaAiCapability::DesignerCopilot => 'Act as a concise senior website designer. Identify the highest-impact next actions for this draft.',
        };

        return <<<PROMPT
You are SYIFA AI, a governed assistant for Malaysian clinic websites. {$mode}
Use only facts present in the supplied authoritative context. Never invent doctors, services, credentials, prices, opening hours, patient outcomes, testimonials, addresses or medical claims. Never diagnose or provide medical advice. Do not alter routing targets, identifiers, tenant data or publication state. Write clear Malaysian Malay unless an existing field is clearly English. Suggestions require human review and must never claim that a change was saved or published. CONTACT has no free-text draft fields, so provide completeness checks and next actions only. Return only the required structured response.
PROMPT;
    }

    private function userPrompt(SyifaAiGenerationRequest $request): string
    {
        try {
            $context = json_encode($request->context, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        } catch (JsonException) {
            throw new SyifaAiProviderException('SYIFA AI context could not be prepared.');
        }
        $section = $request->section === null ? 'WHOLE_WEBSITE' : $request->section->value;
        $instruction = $request->instruction ?? 'No additional instruction.';

        return "Capability: {$request->capability->value}\nSection: {$section}\nUser instruction: {$instruction}\nAuthoritative context:\n{$context}";
    }

    /** @return array<string, mixed> */
    private function schema(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => [
                'title' => ['type' => 'string'],
                'summary' => ['type' => 'string'],
                'suggestions' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'properties' => [
                            'field' => ['type' => 'string'],
                            'label' => ['type' => 'string'],
                            'proposed_value' => ['type' => 'string'],
                            'rationale' => ['type' => 'string'],
                        ],
                        'required' => ['field', 'label', 'proposed_value', 'rationale'],
                    ],
                ],
                'checks' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'properties' => [
                            'label' => ['type' => 'string'],
                            'status' => ['type' => 'string', 'enum' => ['pass', 'improve', 'missing']],
                            'message' => ['type' => 'string'],
                        ],
                        'required' => ['label', 'status', 'message'],
                    ],
                ],
                'next_actions' => ['type' => 'array', 'items' => ['type' => 'string']],
            ],
            'required' => ['title', 'summary', 'suggestions', 'checks', 'next_actions'],
        ];
    }

    /** @param array<string, mixed> $body */
    private function outputText(array $body): string
    {
        $output = $body['output'] ?? null;
        if (! is_array($output)) {
            throw new SyifaAiProviderException('SYIFA AI returned no usable output.');
        }
        foreach ($output as $item) {
            if (! is_array($item) || ! is_array($item['content'] ?? null)) {
                continue;
            }
            foreach ($item['content'] as $content) {
                if (is_array($content) && ($content['type'] ?? null) === 'output_text' && is_string($content['text'] ?? null)) {
                    return $content['text'];
                }
                if (is_array($content) && ($content['type'] ?? null) === 'refusal') {
                    throw new SyifaAiProviderException('SYIFA AI could not assist with this request.');
                }
            }
        }

        throw new SyifaAiProviderException('SYIFA AI returned no usable output.');
    }

    /**
     * @param  array<string, mixed>  $content
     * @param  array<string, mixed>  $body
     */
    private function result(array $content, array $body, string $model): SyifaAiGenerationResult
    {
        $suggestions = $content['suggestions'] ?? [];
        $checks = $content['checks'] ?? [];
        $actions = $content['next_actions'] ?? [];
        if (! is_string($content['title'] ?? null) || ! is_string($content['summary'] ?? null)
            || ! is_array($suggestions) || ! is_array($checks) || ! is_array($actions)) {
            throw new SyifaAiProviderException('SYIFA AI returned an invalid response.');
        }
        $usage = is_array($body['usage'] ?? null) ? $body['usage'] : [];

        /** @var list<array{field: string, label: string, proposed_value: string, rationale: string}> $suggestions */
        /** @var list<array{label: string, status: string, message: string}> $checks */
        /** @var list<string> $actions */
        return new SyifaAiGenerationResult(
            mb_substr($content['title'], 0, 160),
            mb_substr($content['summary'], 0, 1000),
            array_slice($suggestions, 0, 8),
            array_slice($checks, 0, 12),
            array_slice($actions, 0, 8),
            is_string($body['model'] ?? null) ? $body['model'] : $model,
            max(0, (int) ($usage['input_tokens'] ?? 0)),
            max(0, (int) ($usage['output_tokens'] ?? 0)),
        );
    }
}
