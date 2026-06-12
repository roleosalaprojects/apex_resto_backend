<?php

namespace App\Console\Commands\SmsGate;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Bootstrap command — point the SMS Gate relay at our local webhook
 * endpoint for sms:sent, sms:delivered, and sms:failed events.
 *
 * Idempotent: lists existing webhooks first, only registers events
 * that don't already point at our URL.
 *
 * Usage:
 *   php artisan sms-gate:register-webhooks
 *   php artisan sms-gate:register-webhooks --url=http://192.168.0.100:8080/webhooks/sms-gate
 *
 * URL precedence:  --url arg > services.sms_gate.webhook_url > APP_URL/webhooks/sms-gate
 *
 * For local-network setups the URL must be reachable FROM the relay
 * (the Android phone), so use your Mac's LAN IP — not localhost.
 */
class RegisterWebhooksCommand extends Command
{
    protected $signature = 'sms-gate:register-webhooks
                           {--url= : Override the webhook URL the relay should call}
                           {--prune : Delete any existing webhooks NOT in our event set}';

    protected $description = 'Register sms:sent, sms:delivered, sms:failed webhooks with the SMS Gate relay';

    /** Events we wire up. Order matters — register-then-list semantics. */
    private const EVENTS = ['sms:sent', 'sms:delivered', 'sms:failed'];

    public function handle(): int
    {
        $cfg = config('services.sms_gate');
        if (empty($cfg['base_url']) || empty($cfg['username'])) {
            $this->error('SMS Gate not configured. Set SMS_GATE_BASE_URL and SMS_GATE_USERNAME first.');

            return self::FAILURE;
        }

        $webhookUrl = $this->resolveWebhookUrl($cfg);
        $this->info("Webhook URL: {$webhookUrl}");

        $existing = $this->listWebhooks($cfg);
        if ($existing === null) {
            return self::FAILURE;
        }

        $registered = 0;
        $skipped = 0;
        foreach (self::EVENTS as $event) {
            $alreadyThere = collect($existing)->contains(
                fn ($w) => ($w['event'] ?? null) === $event && ($w['url'] ?? null) === $webhookUrl
            );

            if ($alreadyThere) {
                $this->line("  - <fg=gray>{$event}: already registered, skipping</>");
                $skipped++;

                continue;
            }

            if ($this->registerWebhook($cfg, $event, $webhookUrl)) {
                $this->line("  + <fg=green>{$event}: registered</>");
                $registered++;
            } else {
                $this->line("  ! <fg=red>{$event}: failed</>");
            }
        }

        if ($this->option('prune')) {
            $this->pruneStaleWebhooks($cfg, $existing, $webhookUrl);
        }

        $this->info("\nDone. {$registered} new, {$skipped} already in place.");

        return self::SUCCESS;
    }

    private function resolveWebhookUrl(array $cfg): string
    {
        return (string) ($this->option('url')
            ?: ($cfg['webhook_url'] ?: rtrim((string) config('app.url'), '/').'/webhooks/sms-gate'));
    }

    /**
     * @return array<int, array<string, mixed>>|null null on transport failure
     */
    private function listWebhooks(array $cfg): ?array
    {
        try {
            $response = $this->relayClient($cfg)->get($cfg['base_url'].'/webhooks');
        } catch (\Throwable $e) {
            $this->error('Could not reach relay: '.$e->getMessage());

            return null;
        }

        if (! $response->successful()) {
            $this->error('Relay returned HTTP '.$response->status().' on GET /webhooks: '.$response->body());

            return null;
        }

        return $response->json() ?? [];
    }

    private function registerWebhook(array $cfg, string $event, string $webhookUrl): bool
    {
        try {
            $response = $this->relayClient($cfg)->post($cfg['base_url'].'/webhooks', array_filter([
                'event' => $event,
                'url' => $webhookUrl,
                'deviceId' => $cfg['device_id'] ?: null,
            ]));
        } catch (\Throwable $e) {
            $this->line('    error: '.$e->getMessage());

            return false;
        }

        if (! $response->successful()) {
            $this->line('    error: HTTP '.$response->status().' '.$response->body());

            return false;
        }

        return true;
    }

    private function pruneStaleWebhooks(array $cfg, array $existing, string $webhookUrl): void
    {
        foreach ($existing as $w) {
            $matchesOurEventSet = in_array($w['event'] ?? '', self::EVENTS, true)
                && ($w['url'] ?? null) === $webhookUrl;
            if ($matchesOurEventSet) {
                continue;
            }

            $id = $w['id'] ?? null;
            if (! $id) {
                continue;
            }

            try {
                $this->relayClient($cfg)->delete($cfg['base_url'].'/webhooks/'.$id);
                $this->line("  - <fg=yellow>pruned: {$id} ({$w['event']} → {$w['url']})</>");
            } catch (\Throwable $e) {
                $this->line("    prune error on {$id}: ".$e->getMessage());
            }
        }
    }

    private function relayClient(array $cfg): \Illuminate\Http\Client\PendingRequest
    {
        $timeout = (int) ($cfg['timeout'] ?? 15);

        if (($cfg['auth_mode'] ?? 'basic') === 'jwt') {
            // For JWT mode we'd mint a token first; defer that until
            // someone needs to register against the cloud relay. Local
            // (Basic) covers the user's current setup.
            $this->warn('JWT auth mode not implemented in this command; falling back to Basic.');
        }

        return Http::withBasicAuth($cfg['username'], $cfg['password'])
            ->acceptJson()
            ->asJson()
            ->timeout($timeout);
    }
}
