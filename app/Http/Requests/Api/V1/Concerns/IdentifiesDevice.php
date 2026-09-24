<?php

namespace App\Http\Requests\Api\V1\Concerns;

use Illuminate\Support\Str;

/**
 * The phone a token is issued to, e.g. "Pixel 7 · Android 14". Stored as
 * the token name so a customer (or support) can tell their sign-ins apart.
 */
trait IdentifiesDevice
{
    protected function deviceRules(): array
    {
        return ['device_name' => ['nullable', 'string', 'max:100']];
    }

    public function deviceName(): string
    {
        $name = trim((string) $this->input('device_name'));

        return $name !== '' ? Str::limit($name, 100, '') : config('atompay.api.default_device_name');
    }
}
