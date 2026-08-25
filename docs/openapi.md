# OpenAPI 3.1 Specification Generator

Spinx includes an automatic OpenAPI 3.1 specification generator that reflects registered routes, controllers, and PHP 8 attributes.

## Generating the OpenAPI Schema

Run the generator via the CLI:

```bash
spinx openapi:generate
# Default output: public/openapi.json

spinx openapi:generate --output=docs/api-spec.json
```

## Controller Attributes

Annotate controllers with Spinx OpenAPI attributes:

```php
namespace App\Modules\Billing\Infrastructure\Http\Controllers;

use Spinx\OpenApi\Attributes\{ApiSummary, ApiParam, ApiResponse, ApiTag};
use Symfony\Component\HttpFoundation\{Request, JsonResponse};

#[ApiTag('Invoices')]
#[ApiSummary('Retrieve invoice details by ID', 'Returns full billing breakdown and line items')]
#[ApiParam(name: 'id', in: 'path', type: 'integer', description: 'Invoice Primary Key ID')]
#[ApiResponse(status: 200, description: 'Invoice retrieved successfully')]
#[ApiResponse(status: 404, description: 'Invoice not found')]
final class InvoiceShowController
{
    public function __invoke(Request $request, int $id): JsonResponse
    {
        return new JsonResponse(['id' => $id, 'status' => 'paid']);
    }
}
```

### Attribute Reference
- `#[ApiTag(string $tag)]`: Groups the endpoint in Swagger/Redoc UI.
- `#[ApiSummary(string $summary, string $description = '')]`: Endpoint summary and description.
- `#[ApiParam(string $name, string $in = 'path', string $type = 'string', bool $required = true, string $description = '')]`: Documents path, query, header, or cookie parameters.
- `#[ApiResponse(int $status = 200, string $description = 'Successful response')]`: Documents expected HTTP status codes.
