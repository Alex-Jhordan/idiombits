<?php

namespace App\Enums;

enum DailyQuizSessionStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';
    case Expired = 'expired';
}