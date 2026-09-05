<?php

namespace App\Enums;

enum QuizLogInteractionType: string
{
    case Cloze = 'cloze';
    case SentenceScramble = 'sentence_scramble';
    case SelfAssessment = 'self_assessment';
}