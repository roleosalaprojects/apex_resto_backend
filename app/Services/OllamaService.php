<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OllamaService
{
    protected string $baseUrl;

    protected string $model;

    protected int $timeout;

    public function __construct()
    {
        $this->baseUrl = config('services.ollama.url', 'http://localhost:11434');
        $this->model = config('services.ollama.model', 'qwen3:8b');
        $this->timeout = config('services.ollama.timeout', 120);
    }

    /**
     * Generate a completion from Ollama.
     */
    public function generate(string $prompt, array $options = []): ?string
    {
        try {
            $response = Http::timeout($this->timeout)
                ->post("{$this->baseUrl}/api/generate", [
                    'model' => $options['model'] ?? $this->model,
                    'prompt' => $prompt,
                    'stream' => false,
                    'think' => false, // Disable thinking mode for qwen3
                    'options' => [
                        'temperature' => $options['temperature'] ?? 0.7,
                        'num_predict' => $options['max_tokens'] ?? 500,
                    ],
                ]);

            if ($response->successful()) {
                $result = $response->json('response');

                return $this->cleanResponse($result);
            }

            Log::error('Ollama API error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('Ollama connection error', [
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Clean response by removing thinking tags and extra whitespace.
     */
    protected function cleanResponse(?string $response): ?string
    {
        if ($response === null) {
            return null;
        }

        // Remove <think>...</think> blocks (including multiline)
        $cleaned = preg_replace('/<think>.*?<\/think>/s', '', $response);

        // Remove unclosed <think> blocks (thinking until end of string)
        $cleaned = preg_replace('/<think>.*$/s', '', $cleaned);

        // Remove any remaining think tags
        $cleaned = preg_replace('/<\/?think>/i', '', $cleaned);

        // Remove everything before the first capital letter that starts a sentence
        // This handles garbage tokens, Chinese chars, lowercase words, etc.
        $cleaned = preg_replace('/^[^A-Z*#\-•1-9]+/u', '', $cleaned);

        // Trim and clean up multiple newlines
        $cleaned = trim(preg_replace('/\n{3,}/', "\n\n", $cleaned));

        return $cleaned ?: null;
    }

    /**
     * Chat completion with message history.
     */
    public function chat(array $messages, array $options = []): ?string
    {
        try {
            $response = Http::timeout($this->timeout)
                ->post("{$this->baseUrl}/api/chat", [
                    'model' => $options['model'] ?? $this->model,
                    'messages' => $messages,
                    'stream' => false,
                    'think' => false, // Disable thinking mode for qwen3
                    'options' => [
                        'temperature' => $options['temperature'] ?? 0.7,
                        'num_predict' => $options['max_tokens'] ?? 500,
                    ],
                ]);

            if ($response->successful()) {
                $result = $response->json('message.content');

                return $this->cleanResponse($result);
            }

            Log::error('Ollama chat API error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('Ollama chat connection error', [
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Check if Ollama is available.
     */
    public function isAvailable(): bool
    {
        try {
            $response = Http::timeout(5)->get("{$this->baseUrl}/api/tags");

            return $response->successful();
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * List available models.
     */
    public function listModels(): array
    {
        try {
            $response = Http::timeout(10)->get("{$this->baseUrl}/api/tags");

            if ($response->successful()) {
                return $response->json('models', []);
            }

            return [];
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Generate sales insight from historical data.
     */
    public function generateSalesInsight(array $salesData, array $context = []): ?string
    {
        $prompt = $this->buildSalesInsightPrompt($salesData, $context);

        return $this->generate($prompt, [
            'temperature' => 0.5,
            'max_tokens' => 300,
        ]);
    }

    /**
     * Generate reorder recommendation explanation.
     */
    public function generateReorderReason(array $itemData): ?string
    {
        $prompt = <<<PROMPT
You are an inventory management AI assistant for a Philippine retail business. All currency is in Philippine Peso (₱). Based on the following data, provide a brief (2-3 sentences) explanation for the reorder recommendation.

Item: {$itemData['item_name']}
Current Stock: {$itemData['current_stock']} units
Average Daily Sales: {$itemData['avg_daily_sales']} units
Predicted 7-Day Demand: {$itemData['predicted_demand']} units
Days Until Stockout: {$itemData['days_until_stockout']} days
Suggested Reorder Quantity: {$itemData['suggested_quantity']} units

Provide a concise, actionable explanation. Focus on urgency if stockout is imminent. Use ₱ for any monetary values.
PROMPT;

        return $this->generate($prompt, [
            'temperature' => 0.3,
            'max_tokens' => 150,
        ]);
    }

    /**
     * Detect patterns in sales data.
     */
    public function detectPatterns(array $dailySales): ?string
    {
        $salesSummary = collect($dailySales)->map(function ($day) {
            return "{$day['date']}: ₱".number_format($day['total'], 2)." ({$day['transactions']} transactions)";
        })->implode("\n");

        $prompt = <<<PROMPT
You are a business analyst AI for a Philippine retail business. All currency is in Philippine Peso (₱). Analyze this sales data and identify patterns:

{$salesSummary}

Identify:
1. Day-of-week patterns (which days are busiest?)
2. Any unusual spikes or drops
3. Overall trend (increasing/decreasing/stable)

Keep response under 200 words. Be specific with numbers. Always use ₱ symbol for monetary values, never use $.
PROMPT;

        return $this->generate($prompt, [
            'temperature' => 0.4,
            'max_tokens' => 250,
        ]);
    }

    /**
     * Generate batched item insights for top-ranked items.
     *
     * @param  array<int, array{name: string, category: string, score: float, predicted_qty: float, factors: array}>  $items
     * @param  array{date: string, day: string, weather?: string}  $context
     * @return array<int, string>|null Index-to-insight mapping, or null on failure
     */
    public function generateItemInsights(array $items, array $context = []): ?array
    {
        $prompt = $this->buildItemInsightsPrompt($items, $context);

        $response = $this->generate($prompt, [
            'temperature' => 0.4,
            'max_tokens' => 2500,
        ]);

        if ($response === null) {
            return null;
        }

        return $this->parseNumberedInsights($response, count($items));
    }

    /**
     * Build a batched prompt for item insights.
     */
    protected function buildItemInsightsPrompt(array $items, array $context): string
    {
        $date = $context['date'] ?? now()->toDateString();
        $day = $context['day'] ?? now()->format('l');
        $weather = isset($context['weather']) ? "Weather: {$context['weather']}" : '';

        $itemLines = [];
        foreach ($items as $i => $item) {
            $num = $i + 1;
            $factors = implode(', ', $item['factors'] ?? []);
            $itemLines[] = "{$num}. {$item['name']} (Category: {$item['category']}, Score: {$item['score']}, Predicted Qty: {$item['predicted_qty']}, Factors: {$factors})";
        }
        $itemList = implode("\n", $itemLines);

        return <<<PROMPT
You are a retail analytics AI for a Philippine business. Today is {$day}, {$date}. {$weather}

Below are the top-ranked items by sellability score for today. For each item, write exactly ONE concise sentence (max 20 words) explaining why it should sell well today. Focus on actionable insight — mention the day, trend, season, or weather if relevant.

Items:
{$itemList}

Respond with ONLY a numbered list matching the items above. Example format:
1. Strong weekend demand with upward trend — consider front-of-store display.
2. Payday period drives consistent sales — ensure adequate stock levels.
PROMPT;
    }

    /**
     * Parse a numbered list response into an index-to-insight array.
     *
     * @return array<int, string>
     */
    protected function parseNumberedInsights(string $response, int $expectedCount): array
    {
        $insights = [];
        preg_match_all('/^(\d+)\.\s*(.+)$/m', $response, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $index = (int) $match[1] - 1;
            if ($index >= 0 && $index < $expectedCount) {
                $insights[$index] = trim($match[2]);
            }
        }

        return $insights;
    }

    protected function buildSalesInsightPrompt(array $salesData, array $context): string
    {
        $dataJson = json_encode($salesData, JSON_PRETTY_PRINT);
        $contextInfo = ! empty($context) ? 'Additional context: '.json_encode($context) : '';

        return <<<PROMPT
You are a retail analytics AI assistant for a Philippine business. All currency is in Philippine Peso (₱). Analyze this sales data and provide insights:

Data:
{$dataJson}

{$contextInfo}

Provide a brief analysis (3-4 sentences) including:
- Key observations
- Predicted trend for next period
- Any actionable recommendations

Be concise and specific with numbers. Always use ₱ symbol for monetary values, never use $.
PROMPT;
    }
}
