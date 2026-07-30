<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class FileResource extends JsonResource
{
    public function toArray($request): array
    {
        $url = null;

        if ($this->id) {
            $url = url('/api/v1/files/'.$this->id);
        } elseif ($this->s3_key) {
            $url = Storage::url($this->s3_key);
        }

        return [
            'id' => $this->id,
            'uploader_user_id' => $this->uploader_user_id,
            's3_key' => $this->s3_key,
            'mime_type' => $this->mime_type,
            'size_bytes' => $this->size_bytes,
            'width' => $this->width,
            'height' => $this->height,
            'duration' => $this->duration,
            'created_at' => $this->created_at,
            'url' => $url,
        ];
    }
}
