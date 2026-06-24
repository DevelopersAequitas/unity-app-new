<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Membership Email Attachment File IDs
    |--------------------------------------------------------------------------
    |
    | Upload membership email PDFs through /api/v1/files/upload and place the
    | returned file UUIDs here (comma-separated env value). The mailers resolve
    | these IDs through the existing files table instead of hardcoded paths.
    |
    */
    'file_ids' => array_values(array_filter(array_map(
        static fn ($id) => trim((string) $id),
        explode(',', (string) env('MEMBERSHIP_EMAIL_ATTACHMENT_FILE_IDS', ''))
    ))),
];
