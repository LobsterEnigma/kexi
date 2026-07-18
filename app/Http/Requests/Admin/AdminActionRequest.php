<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class AdminActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->is_admin && ! $this->user()->isAccessSuspended();
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }
}
