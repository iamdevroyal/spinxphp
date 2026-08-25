<?php

declare(strict_types=1);

namespace App\Modules\Todo\Infrastructure\Http\Controllers;

use App\Modules\Todo\Infrastructure\Persistence\Models\Todo;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

final class TodoCreateController
{
    public function __invoke(Request $request): RedirectResponse
    {
        $title = trim((string) $request->request->get('title', ''));

        if ($title !== '') {
            Todo::create(['title' => $title, 'done' => false]);
        }

        return new RedirectResponse('/todos');
    }
}
