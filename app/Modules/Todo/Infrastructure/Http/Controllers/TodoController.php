<?php

declare(strict_types=1);

namespace App\Modules\Todo\Infrastructure\Http\Controllers;

use App\Modules\Todo\Application\Services\TodoService;
use Spinx\Http\Request;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Symfony\Component\HttpFoundation\Response;

/**
 * Unified multi-action Todo Controller.
 *
 * Adheres to strict DDD:
 *   - Extracts HTTP inputs
 *   - Calls TodoService for application business logic
 *   - Returns views or redirects.
 */
final class TodoController
{
    public function __construct(
        private readonly TodoService $todoService,
    ) {
    }

    /**
     * List all todos.
     * GET /todos
     */
    public function index(?SymfonyRequest $request = null): Response
    {
        $todos = $this->todoService->listTodos();

        return view('Todo::index', [
            'title' => 'Todos — Spinx Zero-JS Reference Module',
            'todos' => $todos,
        ]);
    }

    /**
     * Store a new todo.
     * POST /todos
     */
    public function store(?SymfonyRequest $request = null): Response
    {
        $title = (string) Request::input('title', '');

        if (trim($title) !== '') {
            $this->todoService->createTodo($title);
        }

        return redirect('/todos');
    }

    /**
     * Toggle a todo's completion status.
     * POST /todos/{id}/toggle
     */
    public function toggle(?SymfonyRequest $request = null, string|int $id = 0): Response
    {
        $this->todoService->toggleTodo($id);

        return redirect('/todos');
    }
}
