<?php

if (!function_exists('roleLabel')) {
    function roleLabel(string $role): string
    {
        $map = [
            'Admin'      => 'Amministrativo ANPAS',
            'Supervisor' => 'Dipendente ANPAS',
            'AdminUser'  => 'Amministrativo Associazione',
            'User'       => 'Utente Associazione',
        ];

        return $map[$role] ?? $role;
    }
}
