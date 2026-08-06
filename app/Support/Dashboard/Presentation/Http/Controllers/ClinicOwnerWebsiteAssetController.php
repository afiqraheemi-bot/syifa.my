<?php

declare(strict_types=1);

namespace App\Support\Dashboard\Presentation\Http\Controllers;

use App\Modules\WebsiteBuilder\Application\WebsiteAsset\UploadWebsiteImageCommand;
use App\Modules\WebsiteBuilder\Application\WebsiteAsset\UploadWebsiteImageService;
use App\Modules\WebsiteBuilder\Application\WebsiteAuthorizationContext;
use App\Modules\WebsiteBuilder\Application\WebsiteContent\ManageWebsiteContentService;
use App\Modules\WebsiteBuilder\Domain\Exceptions\InvalidWebsiteValueException;
use App\Support\Authorization\Application\AuthorizationContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

final readonly class ClinicOwnerWebsiteAssetController
{
    public function __invoke(
        Request $request,
        ManageWebsiteContentService $websites,
        UploadWebsiteImageService $images,
    ): JsonResponse {
        $context = $request->attributes->get(AuthorizationContext::class);
        abort_unless(
            $context instanceof AuthorizationContext
                && $context->tenantId !== null
                && $context->role === 'clinic_owner',
            403,
        );
        $authorization = new WebsiteAuthorizationContext(
            $context->identityId,
            $context->role,
            actorTenantId: $context->tenantId,
        );
        $website = $websites->read($context->tenantId, $authorization)->toArray();

        $input = $request->validate([
            'image' => ['required', 'file', 'max:8192', 'mimetypes:image/jpeg,image/png,image/webp'],
        ]);
        /** @var UploadedFile $image */
        $image = $input['image'];
        $contents = $image->get();
        if (! is_string($contents)) {
            return response()->json(['message' => 'Image could not be read.'], 422);
        }

        try {
            $uploaded = $images->upload(new UploadWebsiteImageCommand(
                $authorization,
                $context->tenantId,
                (string) $website['website_id'],
                $contents,
            ));
        } catch (InvalidWebsiteValueException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json([
            'data' => [
                ...$uploaded->toArray(),
                'url' => route('public-website.assets.show', $uploaded->assetId),
            ],
        ], 201);
    }
}
