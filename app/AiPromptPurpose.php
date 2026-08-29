<?php

namespace App;

enum AiPromptPurpose: string
{
    case SpeakingGrading = 'speaking_grading';
    case Transcription = 'transcription';
    case TextToSpeech = 'text_to_speech';
    case HintGeneration = 'hint_generation';
}
