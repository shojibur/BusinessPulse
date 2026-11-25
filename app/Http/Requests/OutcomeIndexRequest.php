<?php

namespace App\Http\Requests;

use App\Enums\IssueStatus;
use App\Enums\OutcomeResult;
use Illuminate\Foundation\Http\FormRequest;

class OutcomeIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // the controller authorizes viewAny
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }

    public function result(): ?string
    {
        return OutcomeResult::tryFrom((string) $this->query('result'))?->value;
    }

    public function status(): ?string
    {
        return IssueStatus::tryFrom((string) $this->query('status'))?->value;
    }
}
