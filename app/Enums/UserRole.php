<?php

namespace App\Enums;

enum UserRole: string
{
    case Member = 'member';
    case Helper = 'helper';
    case Moderator = 'moderator';
    case Admin = 'admin';
    case Management = 'management';
    case Owner = 'owner';
    case Jury = 'jury';
    case PartnerManager = 'partner_manager';

    public function label(): string
    {
        return match ($this) {
            self::Member => 'Lid',
            self::Helper => 'Helper',
            self::Moderator => 'Moderator',
            self::Admin => 'Admin',
            self::Management => 'Management',
            self::Owner => 'Eigenaar',
            self::Jury => 'Jury',
            self::PartnerManager => 'Partner Manager',
        };
    }
}
