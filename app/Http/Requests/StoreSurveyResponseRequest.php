<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Shape validation only. Whether an answer is actually allowed for a question is
 * decided by App\Services\Responses\SubmitResponse, because only that class
 * sees the published survey schema — so the HTML form and the JSON API cannot
 * disagree.
 */
class StoreSurveyResponseRequest extends FormRequest
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
            // Required by the JSON API only; the HTML form takes it from the URL.
            'site_code' => ['sometimes', 'required', 'string', 'max:64'],
            'answers' => ['required', 'array', 'max:50'],
            // Types are decided by SubmitResponse against the published schema: a
            // rating arrives as a string from the form and as a number from the
            // JSON API, and only the schema knows which is correct.
            'answers.*' => ['nullable'],
            // Client-generated per-attempt key; hashed server-side before storage.
            'idempotency_key' => ['required', 'uuid'],
            // Honeypot: a real person never fills a hidden field.
            'website' => ['prohibited'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'idempotency_key.required' => 'This submission is missing its request key. Please reload the page and try again.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('website'))) {
            // Keep the value so `prohibited` can reject it with a 422 instead of
            // silently dropping spam.
            $this->merge(['website' => trim((string) $this->input('website'))]);
        }
    }
}
