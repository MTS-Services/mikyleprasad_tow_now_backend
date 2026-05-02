<?php

return [
    'conversation_uniqueness' => [
        'two_participants' => (bool) env('MESSAGING_UNIQUE_TWO_PARTICIPANTS', false),
        'group_participants' => (bool) env('MESSAGING_UNIQUE_GROUP_PARTICIPANTS', false),
    ],

    'attachments' => [
        'disk' => env('MESSAGING_ATTACHMENTS_DISK', 'public'),
        'max_per_message' => (int) env('MESSAGING_MAX_ATTACHMENTS_PER_MESSAGE', 5),
        'max_file_kb' => (int) env('MESSAGING_MAX_FILE_KB', 10240),
        'allowed_extensions' => [
            'jpg',
            'jpeg',
            'png',
            'gif',
            'webp',
            'pdf',
            'doc',
            'docx',
            'xls',
            'xlsx',
            'ppt',
            'pptx',
            'txt',
            'csv',
            'zip',
        ],
    ],
];
