<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Device;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DeviceRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'fcm_token' => ['required', 'string', 'max:512'],
            'platform' => ['required', Rule::in(Device::PLATFORMS)],
            'app_version' => ['nullable', 'string', 'max:20'],
        ];
    }
}
