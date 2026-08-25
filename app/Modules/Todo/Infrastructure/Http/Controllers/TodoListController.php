<?php

declare(strict_types=1);

namespace App\Modules\Todo\Infrastructure\Http\Controllers;

use App\Modules\Todo\Infrastructure\Persistence\Models\Todo;
use Spinx\Templating\TemplateRenderer;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class TodoListController
{
    public function __construct(
        private readonly TemplateRenderer $renderer,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $html = $this->renderer->render('Todo::index', [
            'title' => 'My Todos',
            'todos' => Todo::query()->orderBy('created_at')->get(),
        ]);

        return new Response($html);
    }
}
