<?php

declare(strict_types=1);

namespace App\Modules\Todo\Infrastructure\Http\Controllers;

use App\Modules\Todo\Application\Services\TodoService;
use Spinx\Http\Request;
use Spinx\Http\Response;
use Spinx\Validation\ValidationException;

/**
 * Unified multi-action Todo Controller.
 *
 * Adheres to strict DDD:
 *   - Uses Request::validate() for input validation
 *   - Delegates business logic to TodoService
 *   - Returns views or redirects via facades.
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
    public function index(): Response
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
    public function store(): Response
    {
        try {
            $data = Request::validate([
                'title' => 'required|string|min:1|max:255',
            ]);

            $this->todoService->createTodo($data['title']);
        } catch (ValidationException) {
            // Silently skip empty/invalid todo submissions — redirect back
        }

        return redirect('/todos');
    }

    /**
     * Toggle a todo's completion status.
     * POST /todos/{id}/toggle
     */
    public function toggle(string|int $id = 0): Response
    {
        if ((int) $id > 0) {
            $this->todoService->toggleTodo($id);
        }

        return redirect('/todos');
    }
}
