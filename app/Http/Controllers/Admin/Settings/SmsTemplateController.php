<?php

namespace App\Http\Controllers\Admin\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Settings\UpdateSmsTemplateRequest;
use App\Models\Reports\AuditLog;
use App\Models\SmsTemplate;
use App\Services\SmsTemplateRenderer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Admin surface for editing the body copy of system SMS templates.
 * Keys are defined by the app (the queued senders reference them by
 * constant), so create/destroy are intentionally absent — operators
 * can only re-word the text and toggle individual events on/off.
 *
 * Gated by the `sttngs` role flag — same as SMS Logs.
 */
class SmsTemplateController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless((bool) $request->user()?->role?->sttngs, 403);

        $templates = SmsTemplate::query()->orderBy('key')->get();

        return view('admin.settings.sms-templates.index', compact('templates'));
    }

    public function edit(Request $request, SmsTemplate $smsTemplate, SmsTemplateRenderer $renderer): View
    {
        abort_unless((bool) $request->user()?->role?->sttngs, 403);

        // Sample data for the live preview — gives the admin a feel for
        // how the template will look in the wild without having to
        // place a real order.
        $sample = [
            'brand' => 'Quick Baskets',
            'reference' => 'ECO-A1B2C3D4',
            'customer_name' => 'Juan Dela Cruz',
            'total' => '1,234.50',
        ];
        $preview = $renderer->render($smsTemplate->key, $sample) ?? '(template disabled — preview hidden)';

        return view('admin.settings.sms-templates.edit', [
            'template' => $smsTemplate,
            'sample' => $sample,
            'preview' => $preview,
        ]);
    }

    public function update(UpdateSmsTemplateRequest $request, SmsTemplate $smsTemplate): RedirectResponse
    {
        $before = [
            'body' => $smsTemplate->body,
            'enabled' => $smsTemplate->enabled,
        ];

        $smsTemplate->forceFill([
            'body' => $request->input('body'),
            'enabled' => $request->boolean('enabled'),
            'updated_by' => $request->user()?->id,
        ])->save();

        // Customer-facing copy — audit every edit so an investigator
        // can reconstruct what message the customer ACTUALLY received
        // on a given date, not what the template happens to say today.
        AuditLog::record(
            $smsTemplate,
            'sms_template_updated',
            [
                'key' => $smsTemplate->key,
                'body' => $smsTemplate->body,
                'enabled' => (bool) $smsTemplate->enabled,
            ],
            oldValues: $before,
        );

        return redirect()
            ->route('sms-templates.edit', $smsTemplate)
            ->with('success', 'Template updated.');
    }
}
