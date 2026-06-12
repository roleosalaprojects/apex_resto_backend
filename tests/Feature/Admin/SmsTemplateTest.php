<?php

namespace Tests\Feature\Admin;

use App\Models\Employees\Role;
use App\Models\SmsTemplate;
use App\Models\User;
use App\Services\SmsTemplateRenderer;
use Database\Seeders\SmsTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SmsTemplateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SmsTemplateSeeder::class);
    }

    private function adminUser(): User
    {
        $role = Role::factory()->admin()->create();

        return User::factory()->create(['role_id' => $role->id]);
    }

    private function nonAdminUser(): User
    {
        $role = Role::factory()->create(['sttngs' => false]);

        return User::factory()->create(['role_id' => $role->id]);
    }

    public function test_non_admin_cannot_load_index(): void
    {
        $this->actingAs($this->nonAdminUser())
            ->get(route('sms-templates.index'))
            ->assertForbidden();
    }

    public function test_admin_sees_all_seeded_templates(): void
    {
        $response = $this->actingAs($this->adminUser())
            ->get(route('sms-templates.index'))
            ->assertOk();

        foreach ([
            SmsTemplate::KEY_ORDER_VERIFIED,
            SmsTemplate::KEY_ORDER_PAID,
            SmsTemplate::KEY_ORDER_PREPARING,
            SmsTemplate::KEY_ORDER_PICKED_UP,
            SmsTemplate::KEY_ORDER_CANCELLED,
        ] as $key) {
            $response->assertSee($key);
        }
    }

    public function test_admin_can_edit_template_body_and_toggle_enabled(): void
    {
        $admin = $this->adminUser();
        $template = SmsTemplate::findByKey(SmsTemplate::KEY_ORDER_VERIFIED);

        $this->actingAs($admin)
            ->put(route('sms-templates.update', $template), [
                'body' => 'Updated body for {reference}',
                'enabled' => '0',
            ])
            ->assertRedirect(route('sms-templates.edit', $template));

        $template->refresh();
        $this->assertSame('Updated body for {reference}', $template->body);
        $this->assertFalse($template->enabled);
        $this->assertSame($admin->id, $template->updated_by);
    }

    public function test_template_update_writes_audit_log_row(): void
    {
        $admin = $this->adminUser();
        $template = SmsTemplate::findByKey(SmsTemplate::KEY_ORDER_VERIFIED);
        $originalBody = $template->body;

        $this->actingAs($admin)
            ->put(route('sms-templates.update', $template), [
                'body' => 'Edited copy for {reference}',
                'enabled' => '1',
            ])
            ->assertRedirect();

        $audit = \App\Models\Reports\AuditLog::where('auditable_type', SmsTemplate::class)
            ->where('auditable_id', $template->id)
            ->where('event', 'sms_template_updated')
            ->first();

        $this->assertNotNull($audit, 'Admin edits to customer-facing templates must be audited.');
        $this->assertSame($admin->id, $audit->user_id);
        $this->assertSame($originalBody, $audit->old_values['body']);
        $this->assertSame('Edited copy for {reference}', $audit->new_values['body']);
        $this->assertSame(SmsTemplate::KEY_ORDER_VERIFIED, $audit->new_values['key']);
    }

    public function test_renderer_substitutes_known_placeholders(): void
    {
        $renderer = app(SmsTemplateRenderer::class);

        SmsTemplate::findByKey(SmsTemplate::KEY_ORDER_PAID)->update([
            'body' => '{brand}: {reference} ({customer_name}) total {total}',
        ]);

        $rendered = $renderer->render(SmsTemplate::KEY_ORDER_PAID, [
            'brand' => 'Quick Baskets',
            'reference' => 'ECO-ABC',
            'customer_name' => 'Juan',
            'total' => '1,234.50',
        ]);

        $this->assertSame('Quick Baskets: ECO-ABC (Juan) total 1,234.50', $rendered);
    }

    public function test_renderer_leaves_unknown_placeholders_literal(): void
    {
        $renderer = app(SmsTemplateRenderer::class);

        SmsTemplate::findByKey(SmsTemplate::KEY_ORDER_PAID)->update([
            'body' => 'Hello {customer_name}, your code is {magic_code}',
        ]);

        $rendered = $renderer->render(SmsTemplate::KEY_ORDER_PAID, [
            'customer_name' => 'Maria',
        ]);

        $this->assertSame('Hello Maria, your code is {magic_code}', $rendered);
    }

    public function test_renderer_returns_null_for_disabled_template(): void
    {
        $renderer = app(SmsTemplateRenderer::class);

        SmsTemplate::findByKey(SmsTemplate::KEY_ORDER_VERIFIED)->update(['enabled' => false]);

        $this->assertNull($renderer->render(SmsTemplate::KEY_ORDER_VERIFIED, []));
    }

    public function test_renderer_returns_null_for_missing_key(): void
    {
        $renderer = app(SmsTemplateRenderer::class);

        $this->assertNull($renderer->render('does.not.exist', []));
    }
}
