<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;

/**
 * Centralised storage for image attachments — receipts on expenses,
 * proof slips on bank transactions, etc. Both openclaw and mobile/admin
 * upload paths route through here so on-disk layout, filename strategy,
 * and cleanup behavior stay consistent.
 *
 * Files are written to public_path('<dir>/<hash>.<ext>') to match the
 * convention used elsewhere in this codebase (customer images, employee
 * images, etc.) and are served via asset() URLs.
 *
 * The class name is kept as ReceiptStorage to avoid churn in callers,
 * but the storage is generic — pass a relative directory per call.
 */
class ReceiptStorage
{
    public const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'heic'];

    public const MAX_KILOBYTES = 5120;

    public const VALIDATION_RULE = 'required|image|max:5120|mimes:jpg,jpeg,png,webp,heic';

    public const DIR_EXPENSE_RECEIPTS = 'img/receipts/';

    public const DIR_BANK_PROOFS = 'img/bank-proofs/';

    public const DIR_SALE_PAYMENT_PROOFS = 'img/sale-payment-proofs/';

    public const DIR_ORDER_PICKUP_PROOFS = 'img/order-pickup-proofs/';

    /**
     * Persist an uploaded image and return its relative path. Pass a
     * trailing-slash directory like 'img/receipts/'.
     */
    public function store(UploadedFile $file, string $relativeDir): string
    {
        $extension = strtolower($file->guessExtension() ?? $file->getClientOriginalExtension());

        abort_unless(
            in_array($extension, self::ALLOWED_EXTENSIONS, true),
            422,
            'Unsupported image format.'
        );

        $name = bin2hex(random_bytes(8)).'.'.$extension;
        $absoluteDir = public_path($relativeDir);

        if (! is_dir($absoluteDir)) {
            mkdir($absoluteDir, 0755, true);
        }

        $file->move($absoluteDir, $name);

        return $relativeDir.$name;
    }

    /**
     * Delete a previously-stored file. Safe to call when the path is
     * null or already missing.
     */
    public function delete(?string $relativePath): void
    {
        if ($relativePath === null || $relativePath === '') {
            return;
        }

        $absolute = public_path($relativePath);

        if (is_file($absolute)) {
            @unlink($absolute);
        }
    }
}
