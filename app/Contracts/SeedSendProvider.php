<?php

namespace App\Contracts;

use App\Models\SeedSendCampaign;
use App\Models\SeedSendRecipient;

interface SeedSendProvider
{
    /**
     * @return array{provider_message_id: string, payload: array<string, mixed>}
     */
    public function dispatch(SeedSendCampaign $campaign, SeedSendRecipient $recipient): array;
}
