<?php

namespace App\Http\Requests;

use App\Models\CommunityMembership;
use App\Models\Report;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreClaimRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * The user must be authenticated AND an active member of the community
     * that owns the target report. Without the membership check any
     * authenticated user could claim a report in a community they have nothing
     * to do with, defeating community isolation.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        if (! $user) {
            return false;
        }

        $report = $this->route('report');

        if (! $report instanceof Report) {
            return false;
        }

        // A reporter may never claim their own found item.
        if ($report->user_id === $user->id) {
            return false;
        }

        return CommunityMembership::query()
            ->where('community_id', $report->community_id)
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->exists();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // Must reference an existing report that is a "found" report.
            'report_id' => [
                'required',
                'integer',
                // Must be an existing, still-open, "found" report.
                Rule::exists('reports', 'id')->where('type', 'found')->where('status', 'open'),
                // Keep the body consistent with the route-bound report.
                function (string $attribute, mixed $value, \Closure $fail) {
                    $routeReport = $this->route('report');

                    if ($routeReport && (int) $value !== (int) $routeReport->id) {
                        $fail('The report_id does not match the report in the request URL.');
                    }
                },
            ],

            // Private verification answers supplied by the claimant. Each answer
            // is a bounded scalar - unbounded values previously allowed a 200 KB
            // payload (or arbitrarily nested JSON) to be stored per answer.
            'answers' => ['required', 'array', 'min:1', 'max:10'],
            'answers.*' => ['required', 'string', 'max:500'],

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
