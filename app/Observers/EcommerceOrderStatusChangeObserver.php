<?php

namespace App\Observers;

use App\Jobs\Ecommerce\SendOrderUpdateSmsJob;
use App\Models\Ecommerce\EcommerceOrderStatusChange;

/**
 * Status-change rows are immutable audit log entries. We hook the
 * `created` event because that's the only one that fires here — and
 * we use this row's ID (not the order's) as the SMS idempotency key,
 * since each status transition is one row.
 *
 * The job itself decides whether to send (template existence, opt-in,
 * verified phone, notifiable status). Keeping the observer dumb makes
 * it easy to reason about: every status change always tries.
 */
class EcommerceOrderStatusChangeObserver
{
    public function created(EcommerceOrderStatusChange $statusChange): void
    {
        SendOrderUpdateSmsJob::dispatch($statusChange->id);
    }
}
