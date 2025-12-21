<?php

namespace App\Core\Enums;

enum ActivityAction: string
{
    case CREATE = 'create';
    case UPDATE = 'update';
    case DELETE = 'delete';
    case LOGIN = 'login';
    case LOGOUT = 'logout';
    case ROLE_ASSIGNED = 'role_assigned';
    case PERMISSION_GRANTED = 'permission_granted';
}
