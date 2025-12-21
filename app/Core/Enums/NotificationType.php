<?php

namespace App\Core\Enums;

enum NotificationType: string
{
    case SYSTEM = 'system';
    case ADMIN = 'admin';
    case TICKET = 'ticket';
    case SECURITY = 'security';
}

