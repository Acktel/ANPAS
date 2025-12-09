<?php

namespace App\Helpers;

class RoleHelper
{
    public static function label(string $role): string
    {
        return [
            'Admin'      => 'Amministrativo ANPAS',
            'Supervisor' => 'Dipendente ANPAS',
            'AdminUser'  => 'Amministrativo Associazione',
            'User'       => 'Utente Associazione',
        ][$role] ?? $role;
    }
}
