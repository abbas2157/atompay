<?php

namespace App\Services\Security;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Checks a KYC upload before it is stored: CNIC scans and selfies from
 * customers, signed verification forms (image or PDF) from staff. These files
 * are later opened on reviewers' machines, which is what this protects.
 *
 * Two layers:
 *
 *   1. Structural checks, always on, no dependencies. The content must really
 *      be the type it claims; an image must not carry PHP, a phar stub or a
 *      script (polyglot files); a PDF must not carry JavaScript, launch
 *      actions, embedded files, Flash or XFA forms.
 *   2. ClamAV, when config('atompay.kyc.scan.clamav') names a clamd socket.
 *      If clamd is configured but down, the upload is refused unless
 *      `fail_closed` is false.
 *
 * The structural checks are a filter, not antivirus: a PDF can hide active
 * content inside a compressed object stream. Production should run clamd.
 */
class UploadScanner
{
    public const MESSAGE_REJECTED = "This file can't be accepted. Please upload a clear photo (JPG, PNG or WebP) or, for forms, a plain PDF.";

    public const MESSAGE_UNAVAILABLE = "We couldn't check this file just now. Please try again in a few minutes.";

    private const IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

    private const PDF_TYPE = 'application/pdf';

    /** Server-side code or markup that has no business inside a photo. */
    private const IMAGE_PAYLOADS = '/<\?php|__HALT_COMPILER|<script[\s>]|<iframe[\s>]/i';

    /** PDF names that run something, or carry another file, when opened. */
    private const PDF_ACTIVE = '#/(JavaScript|JS|Launch|EmbeddedFile|EmbeddedFiles|RichMedia|XFA|SubmitForm|ImportData|GoToE)(?![A-Za-z0-9])#';

    public function __construct(private readonly ClamAv $clamav) {}

    /**
     * @return string|null a message for the person uploading, or null when the file is accepted
     */
    public function check(UploadedFile $file): ?string
    {
        $reason = $this->structuralProblem($file);

        if ($reason === null && $this->clamav->configured()) {
            try {
                $signature = $this->clamav->scan($file->getRealPath());
                $reason = $signature !== null ? "clamav: {$signature}" : null;
            } catch (RuntimeException $e) {
                Log::error('AtomPay upload scan unavailable', ['error' => $e->getMessage()]);

                if (config('atompay.kyc.scan.fail_closed')) {
                    return self::MESSAGE_UNAVAILABLE;
                }
            }
        }

        if ($reason === null) {
            return null;
        }

        // The file itself is not kept; its hash is enough to recognise it again.
        Log::warning('AtomPay upload rejected', [
            'user' => auth()->id(),
            'reason' => $reason,
            'type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'sha256' => hash_file('sha256', $file->getRealPath()) ?: null,
        ]);

        return self::MESSAGE_REJECTED;
    }

    /** @return string|null why the file is refused, for the log */
    public function structuralProblem(UploadedFile $file): ?string
    {
        $path = $file->getRealPath();
        $content = $path ? @file_get_contents($path) : false;

        if ($content === false || $content === '') {
            return 'unreadable or empty';
        }

        // Sniffed from the bytes, not the name or the browser's Content-Type.
        $type = $file->getMimeType();

        if (in_array($type, self::IMAGE_TYPES, true)) {
            if (@getimagesizefromstring($content) === false) {
                return "not a decodable image ({$type})";
            }
            if (preg_match(self::IMAGE_PAYLOADS, $content, $m)) {
                return 'image carries code: '.$m[0];
            }

            return null;
        }

        if ($type === self::PDF_TYPE) {
            if (! str_contains(substr($content, 0, 1024), '%PDF-')) {
                return 'no PDF header';
            }
            // Names may be hex-escaped (/J#61vaScript) to slip past a plain match.
            $names = preg_replace_callback('/#([0-9A-Fa-f]{2})/', fn ($h) => chr(hexdec($h[1])), $content);
            if (preg_match(self::PDF_ACTIVE, $names, $m)) {
                return 'PDF active content: '.$m[0];
            }

            return null;
        }

        return "type not allowed ({$type})";
    }
}
