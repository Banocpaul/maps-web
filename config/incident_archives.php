<?php
return [
    'automatic' => env('INCIDENT_ARCHIVE_AUTOMATION_ENABLED', env('APP_ENV') !== 'testing'),
];
