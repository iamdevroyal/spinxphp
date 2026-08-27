# Semantic Vector Search & Database Extensions

Spinx natively supports AI vector search, allowing developers to generate text embeddings and execute fast semantic similarity queries over PostgreSQL (`pgvector`), SQLite, and MySQL.

---

## 🚀 Quick Usage

### 1. Generating Embeddings
```php
use Spinx\Database\Vector\Vector;

// Generate 1536-dimensional embedding using OpenAI or local Ollama
$embedding = Vector::embed('Automated Domain-Driven Design in PHP');
// Returns array of floats: [-0.0124, 0.0841, -0.0039, ...]
```

### 2. Performing Similarity Search
```php
use Spinx\Database\Vector\Vector;

// Perform cosine similarity search over documents table
$results = Vector::search(
    table: 'documents',
    vectorColumn: 'embedding',
    queryVector: $embedding,
    filters: ['status' => 'published', 'category_id' => 3],
    limit: 5,
    metric: 'cosine' // 'cosine' (<=>), 'l2' (<->), 'inner_product' (<#>)
);

foreach ($results as $doc) {
    echo "ID: {$doc['id']} — Score/Distance: {$doc['_distance']}\n";
}
```

---

## 🗄️ Database Migrations with Vector Columns

Spinx's schema blueprint includes native `vector()` and `uuid()` column helpers:

```php
use Spinx\Database\Migration;
use Spinx\Database\Schema\Blueprint;
use Spinx\Database\Schema\SchemaBuilder;

return new class extends Migration {
    public function up(SchemaBuilder $schema): void
    {
        // 1. Enable pgvector extension on PostgreSQL
        $schema->enableExtension('vector');

        // 2. Create table with vector column
        $schema->create('knowledge_base', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid');
            $table->string('title');
            $table->text('content');
            $table->vector('embedding', dimensions: 1536);
            $table->timestamps();
        });
    }

    public function down(SchemaBuilder $schema): void
    {
        $schema->dropIfExists('knowledge_base');
    }
};
```

---

## ⚙️ Configuration (`config/vector.php`)

```php
return [
    'default' => env('VECTOR_DRIVER', 'openai'),

    'drivers' => [
        'openai' => [
            'api_key'    => env('OPENAI_API_KEY'),
            'model'      => env('OPENAI_EMBEDDING_MODEL', 'text-embedding-3-small'),
            'dimensions' => (int) env('OPENAI_EMBEDDING_DIMENSIONS', 1536),
            'base_url'   => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
        ],
        'ollama' => [
            'api_key'    => 'ollama',
            'model'      => env('OLLAMA_EMBEDDING_MODEL', 'nomic-embed-text'),
            'dimensions' => 768,
            'base_url'   => env('OLLAMA_BASE_URL', 'http://localhost:11434/v1'),
        ],
    ],
];
```
