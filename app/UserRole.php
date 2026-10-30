<?php

namespace App;

enum UserRole: string
{
    case User = 'user';
    case Moderator = 'moderator';
    case Publisher = 'publisher';
    case SuperAdmin = 'super_admin';
}
