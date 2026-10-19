<?php

namespace App\Services;

class CommunityReportAlertService
{
    public function send(int $groupId): void
    {
        app(ValidatedReportAlertService::class)->send($groupId);
    }
}
