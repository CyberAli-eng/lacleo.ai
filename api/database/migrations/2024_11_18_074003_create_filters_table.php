use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
/**
* Run the migrations.
*/
public function up(): void
{
if (!Schema::hasTable('filters')) {
Schema::create('filters', function (Blueprint $table) {
$table->id();
$table->unsignedBigInteger('filter_group_id');
// Unique identifier like company_size, industry
$table->string('filter_id')->unique();
$table->string('name');
$table->string('description')->nullable();
// Corresponding Elasticsearch field name
$table->string('elasticsearch_field')->nullable();
$table->enum('value_source', [
'elasticsearch', // Dynamic values from ES (companies)
'predefined', // Static values (seniority, company_type)
'specialized', // Complex structures (locations, industries)
'direct', // Direct input (first_name, last_name)
]);
$table->enum('value_type', [
'string', // Simple text (company names, languages)
'number', // Numeric values (headcount, years)
'boolean', // True/false values
'date', // Date values
'array', // Array of values
'location', // Location data (specialized)
'hierarchy', // Hierarchical data (specialized)
]);
$table->enum('input_type', [
'text', // Free text input (first_name)
'select', // Single select from options
'multi_select', // Multiple selections (companies, languages)
'boolean', // Yes/no selection
'hierarchical', // Tree-like selection (locations)
]);
$table->enum('filter_type', ['company', 'contact'])->index();
$table->boolean('is_searchable')->default(false);
$table->boolean('allows_exclusion')->default(false);
$table->boolean('supports_value_lookup')->default(false);
// Points to the class that handles this filter's logic
$table->string('handler_class')->nullable();
// Additional settings for range configuration, validation options
$table->longText('settings')->nullable()->comment('Range configuration, validation options');
$table->unsignedInteger('sort_order');
$table->boolean('is_active')->default(true);
$table->timestamps();

$table->index(['is_active', 'sort_order']);
$table->index('elasticsearch_field');
});
}
}

/**
* Reverse the migrations.
*/
public function down(): void
{
Schema::dropIfExists('filters');
}
};