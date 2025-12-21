<?php

namespace App\Core\Enums;

enum ChannelType: string
{
    case POS = 'pos';
    case PAYMENT = 'payment';
    case WEBHOOK = 'webhook';
    case API = 'api';
}

