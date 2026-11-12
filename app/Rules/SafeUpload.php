<?php

namespace App\Rules;

use App\Services\Security\UploadScanner;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

/**
 * A KYC upload that passed UploadScanner: structural checks always, ClamAV
 * when configured. Put it after the type and size rules.
 */
class SafeUpload implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // Missing or broken uploads are the other rules' to report.
        if (! $value instanceof UploadedFile || ! $value->isValid()) {
            return;
        }

        if ($message = app(UploadScanner::class)->check($value)) {
            $fail($message);
        }
    }
}
