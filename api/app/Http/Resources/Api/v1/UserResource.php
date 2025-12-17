<?php

namespace App\Http\Resources\Api\v1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'email_verified' => $this->email_verified,
            'is_active' => $this->is_active,
            'is_admin' => in_array(strtolower($this->email ?? ''), array_map('strtolower', array_filter(array_map('trim', explode(',', (string) env('ADMIN_EMAILS', '')))))),
            'has_password' => $this->has_password,
            'profile_photo_url' => $this->profile_photo_url,
            'timezone' => $this->timezone,
            'preferences' => $this->preferences,
            'created_at' => $this->created_at,
        ];
    }
}
