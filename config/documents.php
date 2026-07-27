<?php

return [
    'disk' => 'documents',
    'max_upload_kb' => (int) env('DOCUMENT_MAX_UPLOAD_KB', 102400),
    'guest_link_minutes' => (int) env('DOCUMENT_GUEST_LINK_MINUTES', 2),
];
