<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class OpenRouterService
{
    protected string $baseUrl = 'https://openrouter.ai/api/v1';

    /**
     * Validate an API key by attempting to fetch models.
     */
    public function validateKey(string $apiKey): bool
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$apiKey}",
            ])->get("{$this->baseUrl}/models");

            return $response->successful();
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Fetch all available models and filter for free ones.
     */
    public function fetchFreeModels(string $apiKey): array
    {
        $response = Http::withHeaders([
            'Authorization' => "Bearer {$apiKey}",
        ])->get("{$this->baseUrl}/models");

        if (! $response->successful()) {
            return [];
        }

        $models = $response->json('data', []);

        // Filter for free models (pricing is "0" or model ID ends with :free)
        return collect($models)
            ->filter(function ($model) {
                $isFree = str_ends_with($model['id'] ?? '', ':free');

                if (! $isFree && isset($model['pricing'])) {
                    $promptPrice = (float) ($model['pricing']['prompt'] ?? 1);
                    $completionPrice = (float) ($model['pricing']['completion'] ?? 1);
                    $isFree = $promptPrice == 0 && $completionPrice == 0;
                }

                return $isFree;
            })
            ->map(function ($model) {
                return [
                    'id' => $model['id'],
                    'name' => $model['name'] ?? $model['id'],
                    'description' => $model['description'] ?? 'No description available.',
                    'context_length' => $model['context_length'] ?? null,
                    'pricing' => $model['pricing'] ?? null,
                ];
            })
            ->sortBy('name')
            ->values()
            ->toArray();
    }

    /**
     * Send a chat completion request.
     */
    public function chat(string $apiKey, string $model, array $messages): ?string
    {
        $response = Http::withHeaders([
            'Authorization' => "Bearer {$apiKey}",
            'HTTP-Referer' => config('app.url', 'http://localhost'),
            'X-Title' => config('app.name', 'PetraAI'),
        ])->timeout(180)->post("{$this->baseUrl}/chat/completions", [
            'model' => $model,
            'messages' => $messages,
            'provider' => [
                'allow_fallbacks' => true,
                'require_parameters' => false,
            ],
        ]);

        $json = $response->json();

        if (! $response->successful()) {
            $error = $json['error']['message'] ?? $json['error']['code'] ?? 'Unknown error';
            $code = $json['error']['code'] ?? $response->status();

            // Provide helpful error messages
            if (str_contains(strtolower($error), 'provider')) {
                throw new \RuntimeException("The AI provider is temporarily unavailable. Please try again in a moment, or switch to a different model in Settings.");
            }

            if ($code === 429 || str_contains(strtolower($error), 'rate limit')) {
                throw new \RuntimeException("Rate limit reached. Please wait a moment before sending another message.");
            }

            if ($code === 402 || str_contains(strtolower($error), 'insufficient')) {
                throw new \RuntimeException("This model requires credits. Please select a free model in Settings → AI Configuration.");
            }

            throw new \RuntimeException("API error ({$code}): {$error}");
        }

        // Check for error inside a successful response (OpenRouter sometimes does this)
        if (isset($json['error'])) {
            $error = $json['error']['message'] ?? 'Unknown error in response';
            throw new \RuntimeException("The AI provider returned an error. Please try again or switch models.");
        }

        $content = $json['choices'][0]['message']['content'] ?? null;

        if (empty($content)) {
            throw new \RuntimeException("The AI returned an empty response. Please try rephrasing your message or switching models.");
        }

        return $content;
    }
}
