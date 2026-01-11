<?php

namespace App\Enums;

enum CheckoutIntentStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';
    case Expired = 'expired';
    case Canceled = 'canceled';
}
