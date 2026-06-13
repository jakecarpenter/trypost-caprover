<?php

declare(strict_types=1);

namespace App\Http\Requests\App\Automations;

use Illuminate\Foundation\Http\FormRequest;

class RetryRunRequest extends FormRequest
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
            'node_id' => ['nullable', 'string', 'max:255'],
        ];
    }
}
