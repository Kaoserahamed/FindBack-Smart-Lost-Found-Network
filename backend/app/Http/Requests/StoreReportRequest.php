<?php

namespace App\Http\Requests;

use App\Models\CommunityMembership;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreReportRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * The user must be authenticated and an *active* member of the
     * community they are posting the report to.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        if (! $user) {
            return false;
        }

        $communityId = $this->input('community_id');

        if (! $communityId) {
            return false;
        }

        return CommunityMembership::query()
            ->where('community_id', $communityId)
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->exists();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'community_id' => ['required', 'integer', 'exists:communities,id'],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'type' => ['required', Rule::in(['lost', 'found'])],

            'title' => ['required', 'string', 'max:120'],
            'description' => ['required', 'string', 'max:3000'],

            'color' => ['nullable', 'string', 'max:80'],
            'brand' => ['nullable', 'string', 'max:100'],

            'event_at' => ['required', 'date', 'before_or_equal:now'],
            'location_name' => ['required', 'string', 'max:255'],

            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],

            'photos' => ['nullable', 'array', 'max:4'],
            'photos.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
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
            'photos.max' => 'You may upload at most :max photos.',
            'photos.*.image' => 'Each upload must be an image file.',
            'photos.*.mimes' => 'Photos must be a file of type: jpg, jpeg, png, webp.',
            'photos.*.max' => 'Each photo may not be larger than 5 MB.',
        ];
    }
}
