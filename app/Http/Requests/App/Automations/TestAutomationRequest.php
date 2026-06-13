<?php

declare(strict_types=1);

namespace App\Http\Requests\App\Automations;

use Illuminate\Foundation\Http\FormRequest;

class TestAutomationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'with_real_data' => ['nullable', 'boolean'],
        ];
    }
}
