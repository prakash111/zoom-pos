<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ResolvesTenantSyncContext;
use App\Http\Controllers\Controller;
use App\Services\AiImageGeneratorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Mobile parity for the web tenant Products screen's "Generate with AI"
 * button (App\Livewire\Tenant\Products\Index::generateAiPhoto) — wraps the
 * same AiImageGeneratorService so the availability rule (tenant's own key,
 * or a Superadmin-enabled platform key) is identical on both surfaces.
 */
class AiImageApiController extends Controller
{
    use ResolvesTenantSyncContext;

    public function availability(Request $request, AiImageGeneratorService $generator): JsonResponse
    {
        $company = $this->resolveCompany($request);

        return response()->json([
            'success' => true,
            'available' => $generator->isAvailable($company->id),
        ]);
    }

    public function generate(Request $request, AiImageGeneratorService $generator): JsonResponse
    {
        $company = $this->resolveCompany($request);

        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'error' => 'Validation error.', 'details' => $validator->errors()], 422);
        }

        if (! $generator->isAvailable($company->id)) {
            return response()->json(['success' => false, 'error' => 'AI image generation is not configured for this store.'], 422);
        }

        try {
            $imageUrl = $generator->generateProductImage(
                $request->input('name'),
                $request->input('category'),
                null,
                $company->id,
            );

            if (! $imageUrl) {
                return response()->json(['success' => false, 'error' => 'The AI provider returned no image.'], 422);
            }

            return response()->json(['success' => true, 'image_url' => $imageUrl]);
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
}
