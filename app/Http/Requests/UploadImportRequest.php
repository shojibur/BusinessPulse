<?php

namespace App\Http\Requests;

use App\Models\Import;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * Only the file is read from the request. company_id, department_id and uploaded_by in the body are ignored
 * (the action takes them from the signed-in user). File content, type and size are checked by CsvImportReader.
 */
class UploadImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('create', Import::class);
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return ['file' => ['required', 'file']];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.required' => 'Choose a CSV file to upload.',
            'file.file' => 'The file could not be uploaded. It may be larger than the server allows.',
            'file.uploaded' => 'The file could not be uploaded. It may be larger than the server allows (limit 5 MB).',
        ];
    }
}
