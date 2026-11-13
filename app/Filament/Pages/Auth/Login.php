<?php

namespace App\Filament\Pages\Auth;

use Filament\Pages\Auth\Login as BaseLogin;

class Login extends BaseLogin
{
    public function getTitle(): string
    {
        return 'Masuk';
    }

    public function getHeading(): string
    {
        return 'Sistem Manajemen AC';
    }

    public function getSubheading(): string
    {
        return 'Masuk untuk mengelola data dan layanan pendingin udara.';
    }
}
