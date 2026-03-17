<?php

namespace App\Http\Resources;

class CircularDetailResource extends CircularListResource
{
    public function toArray($request): array
    {
        $data = parent::toArray($request);

        $data['content'] = $this->content;
        $data['attachment_url'] = $this->attachment_url;
        $data['is_bookmarked'] = (bool) ($this->is_bookmarked ?? false);
        $data['my_reaction'] = $this->my_reaction;
        $data['read_stats_count'] = (int) ($this->reads_count ?? 0);
        $data['helpful_count'] = (int) ($this->helpful_count ?? 0);
        $data['important_count'] = (int) ($this->important_count ?? 0);

        return $data;
    }
}
