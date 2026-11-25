<?php

namespace App\Http\Requests;

use App\Models\Department;
use App\Models\Issue;
use App\Models\Meeting;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMeetingRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null || ! $user->can('create', Meeting::class)) {
            return false;
        }

        // A Manager naming any other department gets 404 (the department is not theirs to know about).
        if ($user->isManager() && $this->filled('department_id') && (int) $this->input('department_id') !== $user->department_id) {
            abort(404);
        }

        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->user()?->isManager() && ! $this->filled('department_id')) {
            $this->merge(['department_id' => $this->user()->department_id]);
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'min:1', 'max:150'],
            'meeting_date' => ['required', 'date_format:Y-m-d'],
            'department_id' => ['required', 'integer', Rule::exists(Department::class, 'id')->where('company_id', $this->user()->company_id)],
            'issue_ids' => ['nullable', 'array', 'max:10'],
            'issue_ids.*' => ['integer', 'distinct', fn ($attribute, $value, $fail) => $this->issueInDepartment((int) $value) ?: $fail('The selected issue does not belong to the meeting department.')],
        ];
    }

    protected function issueInDepartment(int $issueId): bool
    {
        return Issue::query()->whereKey($issueId)->where('department_id', (int) $this->input('department_id'))->exists();
    }
}
