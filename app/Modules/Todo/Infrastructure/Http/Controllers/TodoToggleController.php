<?php

declare(strict_types=1);

namespace App\Modules\Todo\Infrastructure\Http\Controllers;

use App\Modules\Todo\Infrastructure\Persistence\Models\Todo;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

final class TodoToggleController
{
    public function __invoke(Request $request, string $id): RedirectResponse
    {
        $todo = Todo::find($id);

        if ($todo !== null) {
            $todo->update(['done' => !$todo->done]);
        }

        return new RedirectResponse('/todos');
    }
}
