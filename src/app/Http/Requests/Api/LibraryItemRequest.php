<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class LibraryItemRequest extends FormRequest
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
        return [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'source_type' => 'required|in:upload,url,youtube',
            'file' => 'required_if:source_type,upload|file|mimes:mp3,mp4,m4a,wav,ogg|max:512000',
            'source_url' => 'required_if:source_type,url,youtube|url|max:2048',
        ];
    }

    /**
     * Get custom error messages for validation rules.
     */
    public function messages(): array
    {
        return [
            'title.required' => 'A title is required.',
            'title.max' => 'The title may not be greater than 255 characters.',
            'description.max' => 'The description may not be greater than 1000 characters.',
            'source_type.required' => 'A source type is required.',
            'source_type.in' => 'The source type must be one of: upload, url, youtube.',
            'file.required_if' => 'A file is required when source type is upload.',
            'file.mimes' => 'The file must be an audio or video file (MP3, MP4, M4A, WAV, OGG).',
            'file.max' => 'The file may not be greater than 512 MB.',
            'source_url.required_if' => 'A source URL is required when source type is url or youtube.',
            'source_url.url' => 'The source URL must be a valid URL.',
            'source_url.max' => 'The source URL may not be greater than 2048 characters.',
        ];
    }
}
