<?php

namespace App\Http\Requests\Advertisement;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $mediaType = $this->input('media_type', 'image');
        $isVideo = $mediaType === 'video';

        $mediaRules = $isVideo
            ? ['required', 'file', 'mimes:mp4,webm,mov', 'max:102400'] // 100MB for video
            : ['required', 'file', 'image', 'mimes:jpeg,png,jpg', 'max:10240']; // 10MB for image

        $durationRules = $isVideo
            ? ['required', 'integer', 'min:5', 'max:300'] // 5 sec to 5 min for video
            : ['required', 'integer', 'min:5', 'max:60']; // 5 sec to 60 sec for image

        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'media_type' => ['required', Rule::in(['image', 'video'])],
            'media' => $mediaRules,
            'duration' => $durationRules,
            'status' => ['required', 'boolean'],
            'display_order' => ['nullable', 'integer', 'min:0'],
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
            'media.required' => 'Please upload an image or video file.',
            'media.max' => $this->input('media_type') === 'video'
                ? 'Video file must not exceed 100MB.'
                : 'Image file must not exceed 10MB.',
            'duration.min' => 'Duration must be at least 5 seconds.',
            'duration.max' => $this->input('media_type') === 'video'
                ? 'Video duration must not exceed 5 minutes (300 seconds).'
                : 'Image duration must not exceed 60 seconds.',
        ];
    }
}
