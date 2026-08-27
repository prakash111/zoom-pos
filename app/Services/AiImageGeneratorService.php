<?php

namespace App\Services;

use App\Models\Configuration;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class AiImageGeneratorService
{
    public function generateProductImage(string $productName, ?string $category = null, ?string $provider = null, ?string $companyId = null): ?string
    {
        $provider ??= $this->setting('default_ai_provider', 'openai', $companyId);
        $prompt = "Commercial studio product photography of {$productName}, categorized under '".($category ?: 'General')."', isolated on a clean minimalist background, soft lighting, 4k, photorealistic retail catalog shot. No text, logos, watermark, hands, or people.";

        return match ($provider) {
            'gemini' => $this->generateWithGemini($prompt, $companyId, (string) $this->setting('gemini_model', 'imagen-3.0-generate-002', $companyId)),
            'claude' => $this->generateWithClaude($productName, $category, $companyId, (string) $this->setting('claude_model', 'claude-3-5-sonnet-20241022', $companyId)),
            default => $this->generateWithOpenAi($prompt, $companyId, (string) $this->setting('openai_model', 'dall-e-3', $companyId)),
        };
    }

    protected function generateWithOpenAi(string $prompt, ?string $companyId = null, string $model = 'dall-e-3'): ?string
    {
        $key = $this->key('openai_api_key', 'openai.api_key', $companyId);

        if (in_array($model, ['gpt-4o', 'gpt-4o-mini'], true)) {
            $refinement = $this->request()->withToken($key)->post('https://api.openai.com/v1/chat/completions', [
                'model' => $model,
                'messages' => [['role' => 'user', 'content' => "Rewrite this as one precise photorealistic e-commerce image prompt. Return only the prompt: {$prompt}"]],
                'max_tokens' => 250,
            ]);
            $refinement->throw();
            $prompt = trim((string) $refinement->json('choices.0.message.content')) ?: $prompt;
            $model = 'dall-e-3';
        }

        $isLegacy = $model === 'dall-e-2';
        $response = $this->request()->withToken($key)->post('https://api.openai.com/v1/images/generations', [
            'model' => $model, 'prompt' => $prompt, 'n' => 1,
            'size' => $isLegacy ? '512x512' : '1024x1024',
            ...($isLegacy ? [] : ['quality' => 'standard']),
            'response_format' => 'url',
        ]);
        $response->throw();

        if ($url = $response->json('data.0.url')) {
            return $this->downloadAndStoreImage($url);
        }
        if ($base64 = $response->json('data.0.b64_json')) {
            return $this->storeBase64($base64, 'png');
        }

        return null;
    }

    protected function generateWithGemini(string $prompt, ?string $companyId = null, string $model = 'imagen-3.0-generate-002'): ?string
    {
        $key = $this->key('gemini_api_key', 'gemini.api_key', $companyId);

        if (in_array($model, ['gemini-2.5-flash', 'gemini-1.5-flash'], true)) {
            $refinement = $this->request()->post('https://generativelanguage.googleapis.com/v1beta/models/'.rawurlencode($model).':generateContent?key='.urlencode($key), [
                'contents' => [['parts' => [['text' => "Rewrite this as one precise photorealistic e-commerce image prompt. Return only the prompt: {$prompt}"]]]],
            ]);
            $refinement->throw();
            $prompt = trim((string) $refinement->json('candidates.0.content.parts.0.text')) ?: $prompt;
            $model = 'imagen-3.0-generate-002';
        }

        $response = $this->request()->post('https://generativelanguage.googleapis.com/v1beta/models/'.rawurlencode($model).':predict?key='.urlencode($key), [
            'instances' => [['prompt' => $prompt]],
            'parameters' => ['sampleCount' => 1, 'aspectRatio' => '1:1'],
        ]);
        $response->throw();

        $base64 = $response->json('predictions.0.bytesBase64Encoded')
            ?? $response->json('predictions.0.bytesBase64Encoded.bytes');

        return $base64 ? $this->storeBase64($base64, 'jpg') : null;
    }

    protected function generateWithClaude(string $name, ?string $category, ?string $companyId = null, string $model = 'claude-3-5-sonnet-20241022'): ?string
    {
        $key = $this->key('claude_api_key', 'anthropic.api_key', $companyId);
        $response = $this->request()->withHeaders([
            'x-api-key' => $key, 'anthropic-version' => '2023-06-01',
        ])->post('https://api.anthropic.com/v1/messages', [
            'model' => $model, 'max_tokens' => 250,
            'messages' => [['role' => 'user', 'content' => "Write one detailed photorealistic e-commerce image prompt for '{$name}' in category '".($category ?: 'General')."'. Return only the prompt."]],
        ]);
        $response->throw();

        // Claude refines the visual direction; OpenAI performs the image synthesis.
        $prompt = trim((string) $response->json('content.0.text')) ?: "Commercial studio photo of {$name}";

        return $this->generateWithOpenAi($prompt, $companyId, 'dall-e-3');
    }

    protected function request(): PendingRequest
    {
        return Http::acceptJson()->timeout(120)->retry(2, 500);
    }

    protected function downloadAndStoreImage(string $url): string
    {
        $response = Http::timeout(120)->get($url);
        $response->throw();
        $path = 'products/'.Str::uuid().'.png';
        Storage::disk('public')->put($path, $response->body());

        return Storage::disk('public')->url($path);
    }

    protected function storeBase64(string $contents, string $extension): string
    {
        $decoded = base64_decode($contents, true);
        if ($decoded === false) {
            throw new RuntimeException('The image provider returned invalid image data.');
        }
        $path = 'products/'.Str::uuid().'.'.$extension;
        Storage::disk('public')->put($path, $decoded);

        return Storage::disk('public')->url($path);
    }

    protected function key(string $setting, string $config, ?string $companyId): string
    {
        $key = (string) $this->setting($setting, config('services.'.$config), $companyId);
        if ($key === '') {
            throw new RuntimeException('The selected AI provider is not configured in Settings > API & Integrations.');
        }

        return $key;
    }

    protected function setting(string $key, mixed $default, ?string $companyId): mixed
    {
        if (! $companyId) {
            return tenant_setting($key, $default);
        }

        return Configuration::withoutGlobalScopes()
            ->where('company_id', $companyId)->where('key', $key)->value('value') ?? $default;
    }
}
