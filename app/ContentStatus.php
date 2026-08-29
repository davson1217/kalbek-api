<?php

namespace App;

enum ContentStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Archived = 'archived';
}
