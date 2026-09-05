<?php

namespace App\Enums;

enum PhraseStatus: string
{
    case Captured = 'captured';
    case Queued = 'queued';
    case InProgress = 'in_progress';
    case Learned = 'learned';
    case ReLearning = 're_learning';
}