<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Application\SyifaAi;

use App\Modules\Booking\Contracts\Queries\ActiveServiceCatalogueReaderInterface;
use App\Modules\WebsiteBuilder\Application\SyifaAi\Exceptions\SyifaAiNotReadyException;
use App\Modules\WebsiteBuilder\Application\WebsiteContent\ManageWebsiteContentService;
use App\Modules\WebsiteBuilder\Application\WebsiteDraft\LoadDraftWebsiteContent;
use App\Modules\WebsiteBuilder\Application\WebsiteDraft\ManageWebsiteDraftContentService;
use App\Modules\WebsiteBuilder\Contracts\SyifaAi\SyifaAiProviderInterface;
use App\Modules\WebsiteBuilder\Contracts\SyifaAi\SyifaAiUsageRepositoryInterface;

final readonly class AssistWebsiteDraftService
{
    public function __construct(
        private ManageWebsiteDraftContentService $drafts,
        private ManageWebsiteContentService $websites,
        private ActiveServiceCatalogueReaderInterface $services,
        private SyifaAiProviderInterface $provider,
        private SyifaAiUsageRepositoryInterface $usage,
    ) {}

    public function assist(AssistWebsiteDraftCommand $command): SyifaAiGenerationResult
    {
        if ($command->capability === SyifaAiCapability::ContentAssistant && $command->section === null) {
            throw new SyifaAiNotReadyException('Choose a Website section before requesting content suggestions.');
        }

        $used = $this->usage->tokensUsedThisMonth($command->tenantId);
        $limit = max(0, (int) config('syifa_ai.monthly_tenant_token_limit', 0));
        if ($limit > 0 && $used >= $limit) {
            throw new SyifaAiNotReadyException('The monthly SYIFA AI allowance has been reached for this clinic.');
        }

        $draft = $this->drafts->load(new LoadDraftWebsiteContent(
            $command->authorization,
            $command->tenantId,
            $command->websiteId,
        ))->toArray();
        $website = $this->websites->read($command->tenantId, $command->authorization)->toArray();
        $catalogue = array_map(
            static fn ($service): array => ['id' => $service->id, 'name' => $service->name],
            $this->services->forTenant($command->tenantId),
        );
        $context = [
            'clinic' => [
                'name' => $website['branding']['clinic_name'],
                'tagline' => $website['branding']['tagline'],
                'contact_email' => $website['branding']['contact_email'],
                'contact_phone' => $website['branding']['contact_phone'],
                'address' => $website['branding']['address'],
            ],
            'template_id' => $website['template_id'],
            'seo' => $website['seo'],
            'enabled_sections' => array_values(array_filter(
                $website['sections'],
                static fn (array $section): bool => $section['enabled'] === true,
            )),
            'active_services' => $catalogue,
            'draft_version' => $draft['version'],
            'draft_sections' => $command->section === null
                ? $draft['sections']
                : array_values(array_filter(
                    $draft['sections'],
                    static fn (array $section): bool => $section['type'] === $command->section->value,
                )),
        ];

        $result = $this->provider->generate(new SyifaAiGenerationRequest(
            $command->capability,
            $command->section,
            $this->normalizeInstruction($command->instruction),
            $context,
            hash_hmac('sha256', $command->authorization->actorId, (string) config('app.key')),
        ));
        $this->usage->record(new SyifaAiUsageRecord(
            $command->tenantId,
            $command->websiteId,
            $command->authorization->actorId,
            $command->capability,
            $command->section,
            $result->model,
            'completed',
            $result->inputTokens,
            $result->outputTokens,
        ));

        return $result;
    }

    private function normalizeInstruction(?string $instruction): ?string
    {
        if ($instruction === null) {
            return null;
        }
        $normalized = trim(preg_replace('/\s+/', ' ', $instruction) ?? '');

        return $normalized === '' ? null : mb_substr($normalized, 0, 600);
    }
}
