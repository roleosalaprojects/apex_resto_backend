<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class AiService
{
    protected bool $anthropicAvailable;

    protected bool $ollamaAvailable;

    public function __construct(
        protected AnthropicService $anthropic,
        protected OllamaService $ollama
    ) {
        $this->anthropicAvailable = $anthropic->isAvailable();
        $this->ollamaAvailable = $ollama->isAvailable();
    }

    /**
     * Check if any AI provider is available.
     */
    public function isAvailable(): bool
    {
        return $this->anthropicAvailable || $this->ollamaAvailable;
    }

    /**
     * Get the name of the active AI provider.
     */
    public function activeProvider(): ?string
    {
        if ($this->anthropicAvailable) {
            return 'Claude';
        }

        if ($this->ollamaAvailable) {
            return 'Ollama';
        }

        return null;
    }

    /**
     * Generate a completion, trying Anthropic first then falling back to Ollama.
     */
    public function generate(string $prompt, array $options = []): ?string
    {
        if ($this->anthropicAvailable) {
            $result = $this->anthropic->generate($prompt, $options);
            if ($result !== null) {
                return $result;
            }
            Log::warning('Anthropic generation failed, falling back to Ollama');
        }

        if ($this->ollamaAvailable) {
            return $this->ollama->generate($prompt, $options);
        }

        return null;
    }

    /**
     * Generate sales insight from historical data.
     */
    public function generateSalesInsight(array $salesData, array $context = []): ?string
    {
        if ($this->anthropicAvailable) {
            $result = $this->anthropic->generateSalesInsight($salesData, $context);
            if ($result !== null) {
                return $result;
            }
            Log::warning('Anthropic sales insight failed, falling back to Ollama');
        }

        if ($this->ollamaAvailable) {
            return $this->ollama->generateSalesInsight($salesData, $context);
        }

        return null;
    }

    /**
     * Generate reorder recommendation explanation.
     */
    public function generateReorderReason(array $itemData): ?string
    {
        if ($this->anthropicAvailable) {
            $result = $this->anthropic->generateReorderReason($itemData);
            if ($result !== null) {
                return $result;
            }
            Log::warning('Anthropic reorder reason failed, falling back to Ollama');
        }

        if ($this->ollamaAvailable) {
            return $this->ollama->generateReorderReason($itemData);
        }

        return null;
    }

    /**
     * Detect patterns in sales data.
     */
    public function detectPatterns(array $dailySales): ?string
    {
        if ($this->anthropicAvailable) {
            $result = $this->anthropic->detectPatterns($dailySales);
            if ($result !== null) {
                return $result;
            }
            Log::warning('Anthropic pattern detection failed, falling back to Ollama');
        }

        if ($this->ollamaAvailable) {
            return $this->ollama->detectPatterns($dailySales);
        }

        return null;
    }

    /**
     * Generate batched item insights for top-ranked items.
     *
     * @param  array<int, array{name: string, category: string, score: float, predicted_qty: float, factors: array}>  $items
     * @param  array{date: string, day: string, weather?: string}  $context
     * @return array<int, string>|null
     */
    public function generateItemInsights(array $items, array $context = []): ?array
    {
        if ($this->anthropicAvailable) {
            $result = $this->anthropic->generateItemInsights($items, $context);
            if ($result !== null) {
                return $result;
            }
            Log::warning('Anthropic item insights failed, falling back to Ollama');
        }

        if ($this->ollamaAvailable) {
            return $this->ollama->generateItemInsights($items, $context);
        }

        return null;
    }
}
