<?php

namespace App\Services;

use App\Models\User;

class ResidentSubscriptionService
{
    public static function normalizePhone(string $phone): string
    {
        $phone = preg_replace('/[\s()\-]/', '', trim($phone));
        if (preg_match('/^09\d{9}$/', $phone)) {
            return '+63'.substr($phone, 1);
        }
        if (preg_match('/^639\d{9}$/', $phone)) {
            return '+'.$phone;
        }

        return $phone;
    }

    public function sync(User $user): void
    {
        $user->smsRecipient()->updateOrCreate(['user_id' => $user->id], [
            'full_name' => $user->name, 'phone_number' => $user->contact_number,
            'position' => 'Public Resident', 'barangay_id' => $user->barangay_id,
            'office_or_barangay' => $user->barangay()->value('name'),
            'receive_flood_alerts' => $user->receive_flood_alerts,
            'receive_fire_alerts' => $user->receive_fire_alerts,
            'receive_general_alerts' => false,
            'is_active' => $user->is_active && ($user->receive_flood_alerts || $user->receive_fire_alerts),
            'created_by' => $user->id, 'updated_by' => $user->id,
        ]);
    }
}
