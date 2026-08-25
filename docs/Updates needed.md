Database
// Implement Existence, muilti-column reads
//  Model::query()->selectWith('id','name','password')->get()
//  Model::query()->selectWithout('id','name')->get()
// Filtering
// Add when/then to transform data on db level when a certain criteria is met. eg when user is admin then run a method, handle a task or update a certain table in a closure. Order::query()->when('total' , '>' , 600)->then( fn(use $order){ do something here}); can also chain when like the if more like match in laravel

// Atomic updates (computed in the database, not read-then-write —
// avoids a lost-update race between two concurrent requests)
// Implement Order::upsert(), Order::atomic() to perform atomic actions on a single model like increment, decrement at the same time, also implement DB::transaction

# Preview
implement a php spinx preview --mobile twebview preview that displays in a mobile phone container, that is we serve the app inside a webview mobile container that we can select different phone makes and resolutions to preview the website in different resolutions

# Routing & Controllers
// Implement named routes, route grouping, route prefixing and also improve on the writability to be more simpler, especially the defaults and methods parts for adding controller and middlewares and method types need to be simpler, somthing like 
// Route::get(['orders.show', '/orders/{id}'])->middleware(['auth', 'rate_limit'])->controller(order_controller); where on the middleware and controller classes we can expose their aliases to be used on the module.php file

// Give controller their own closure 'controllers' => static function... with alias exposure to be used on the route closure in the module.php so we have 'routes', 'services' and 'controller' closures and for each controller we add in the controller closure we expose its alias to be used in the route closure as ->controller(order_controller) instead of the full defaults: ['_controller' => OrderController::class].
Same thing for the Middlewares we // We need to add a method that exposes the middlewares alias to be used on the route or elsewere
