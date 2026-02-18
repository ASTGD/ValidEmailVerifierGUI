<?php

namespace App\Http\Requests\Admin;

use App\Support\Roles;
use Illuminate\Foundation\Http\FormRequest;

class RetrySeedSendCampaignRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user && method_exists($user, 'hasRole') && $user->hasRole(Roles::ADMIN);
    }

    public function rules(): array
    {
        return [
            'max_recipients' => ['nullable', 'integer', 'min:1', 'max:5000'],
        ];
    }
}
