<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreClaimRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * Only authenticated users may submit a claim. Further authorization
     * (e.g. that the report is claimable) is handled during validation.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // Must reference an existing report that is a "found" report.
            'report_id' => [
                'required',
                'integer',
                Rule::exists('reports', 'id')->where('type', 'found'),
                // Keep the body consistent with the route-bound report.
                function (string $attribute, mixed $value, \Closure $fail) {
                    $routeReport = $this->route('report');

                    if ($routeReport && (int) $value !== (int) $routeReport->id) {
                        $fail('The report_id does not match the report in the request URL.');
                    }
                },
            ],

            // Private verification answers supplied by the claimant.
            'answers' => ['required', 'array', 'min:1'],
            'answers.*' => ['required'],

            // Optional supporting evidence (image or PDF), stored privately.
            'evidence_path' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'report_id.exists' => 'The selected report is invalid or is not a found item.',
            'answers.required' => 'Verification answers are required to submit a claim.',
            'answers.min' => 'Provide at least one verification answer.',
            'evidence_path.mimes' => 'Evidence must be a file of type: jpg, jpeg, png, pdf.',
            'evidence_path.max' => 'Evidence may not be larger than 5 MB.',
        ];
    }
}
