<?php

namespace App;

enum SpeakingAttemptStatus: string
{
    case Pending = 'pending';
    case Transcribed = 'transcribed';
    case Graded = 'graded';
    case Failed = 'failed';
}
