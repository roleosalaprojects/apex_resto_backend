<?php

namespace App\Services;

use App\Models\SmsTemplate;

/**
 * Resolves a template by key, substitutes placeholders, returns the
 * rendered string ready for VeroSMS dispatch.
 *
 * Returns null when:
 *  - the key doesn't exist (template was deleted or seeder hasn't run), or
 *  - the row exists but enabled=false (admin temporarily muted the event).
 *
 * Both are valid "skip the SMS" signals — callers should treat null as
 * "no message to send" rather than an error condition.
 */
class SmsTemplateRenderer
{
    /**
     * Render a template, substituting {placeholder} tokens with $vars.
     * Unknown placeholders are left literal so admins can SEE typos in
     * the rendered preview instead of silently swallowing them.
     *
     * @param  array<string, scalar|null>  $vars
     */
    public function render(string $key, array $vars): ?string
    {
        $template = SmsTemplate::findByKey($key);

        if (! $template || ! $template->enabled) {
            return null;
        }

        $body = $template->body;

        foreach ($vars as $name => $value) {
            $body = str_replace('{'.$name.'}', (string) ($value ?? ''), $body);
        }

        return $body;
    }
}
