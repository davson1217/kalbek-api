<?php

namespace App;

enum UserRole: string
{
    case Learner = 'learner';
    case Teacher = 'teacher';
    case Admin = 'admin';

    public function canAccessCms(): bool
    {
        return $this !== self::Learner;
    }
}
