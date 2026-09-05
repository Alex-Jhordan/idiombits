<?php

namespace App\Enums;

enum QuizLogType: string
{
    case Ordinary = 'ordinary';
    case Audit = 'audit';
}