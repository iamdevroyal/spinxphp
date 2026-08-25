<?php

declare(strict_types=1);

namespace App\Modules\Auth\Infrastructure\Http\Controllers;

use Spinx\Auth\Auth;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class LogoutController
{
    public function __invoke(Request $request): Response
    {
        Auth::logout();

        return redirect('/login');
    }
}
