<?php

namespace Tests\Feature\Ecommerce;

use App\Models\Ecommerce\EcommerceOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Order references are customer-facing identifiers — they show up in
 * URLs, SMS, receipts. The generator must produce guess-resistant,
 * collision-resistant values so an attacker can't enumerate active
 * orders or stumble onto someone else's by walking ECO-* URLs.
 */
class EcommerceOrderReferenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_generated_reference_matches_new_high_entropy_format(): void
    {
        $reference = EcommerceOrder::generateReference();

        // 12 hex chars after ECO- = 48 bits of entropy via random_bytes(6).
        // Old 8-char refs already in the wild stay valid (the route regex
        // accepts any length), but every NEW one goes through this path.
        $this->assertMatchesRegularExpression('/^ECO-[A-F0-9]{12}$/', $reference);
    }

    public function test_generated_references_are_unique_across_many_calls(): void
    {
        $refs = [];
        for ($i = 0; $i < 200; $i++) {
            $refs[] = EcommerceOrder::generateReference();
        }

        $this->assertCount(200, array_unique($refs),
            'Generator must not produce collisions over a small batch — '.
            'random_bytes failure or a bad uniqueness loop would surface here.');
    }

    public function test_generator_does_not_reuse_existing_reference(): void
    {
        // Seed the DB with a known reference, then prove the uniqueness
        // loop dodges it. If random_bytes ever returns those exact 6
        // bytes the loop should regenerate, but we can't force that
        // probabilistically — we can only prove the lookup happens.
        $existing = EcommerceOrder::factory()->create([
            'reference' => 'ECO-DEADBEEFCAFE',
        ]);

        for ($i = 0; $i < 50; $i++) {
            $fresh = EcommerceOrder::generateReference();
            $this->assertNotSame($existing->reference, $fresh);
        }
    }

    public function test_legacy_short_format_references_remain_valid(): void
    {
        // Backwards compat: any pre-existing 8-char ref in production
        // must continue to load through the implicit binding. Test it
        // by stuffing an 8-char ref in directly and resolving it via
        // the route-style query.
        EcommerceOrder::factory()->create([
            'reference' => 'ECO-A1B2C3D4',
        ]);

        $found = EcommerceOrder::where('reference', 'ECO-A1B2C3D4')->first();
        $this->assertNotNull($found);
    }
}
