<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class DataModelVerificationTest extends TestCase
{
    /**
     * Verifica que existan exactamente las 6 tablas en el esquema 'library'.
     */
    public function test_library_schema_has_exactly_six_tables(): void
    {
        $tables = DB::table('information_schema.tables')
            ->where('table_schema', 'library')
            ->pluck('table_name')
            ->sort()
            ->values()
            ->all();

        $expected = ['book', 'category', 'loan', 'loan_item', 'member', 'user'];

        $this->assertSame($expected, $tables);
    }

    /**
     * Verifica data-model.md §3 y §10: Ninguna columna lleva DEFAULT.
     */
    public function test_no_column_has_default_value(): void
    {
        $columnsWithDefault = DB::table('information_schema.columns')
            ->where('table_schema', 'library')
            ->whereNotNull('column_default')
            ->select('table_name', 'column_name', 'column_default')
            ->get();

        $this->assertCount(0, $columnsWithDefault, 'Ninguna columna debe tener un valor DEFAULT definido en la BD');
    }

    /**
     * Verifica la siembra estricta de las cinco categorías fijas con UUIDs literales.
     */
    public function test_five_categories_are_properly_seeded(): void
    {
        $categories = DB::table('library.category')
            ->orderBy('name')
            ->get();

        $this->assertCount(5, $categories);

        $expectedMap = [
            'Ciencia' => '22222222-2222-4222-8222-222222222222',
            'Historia' => '44444444-4444-4444-8444-444444444444',
            'Infantil' => '55555555-5555-4555-8555-555555555555',
            'Novela' => '11111111-1111-4111-8111-111111111111',
            'Tecnología' => '33333333-3333-4333-8333-333333333333',
        ];

        foreach ($categories as $cat) {
            $this->assertArrayHasKey($cat->name, $expectedMap);
            $this->assertSame($expectedMap[$cat->name], $cat->id);
        }
    }

    /**
     * Verifica la existencia de las restricciones CHECK de inventario y rol.
     */
    public function test_check_constraints_exist_in_database(): void
    {
        $constraints = DB::select("
            SELECT c.conname, pg_get_constraintdef(c.oid) as def
            FROM pg_constraint c
            JOIN pg_namespace n ON n.oid = c.connamespace
            WHERE n.nspname = 'library' AND c.contype = 'c'
            ORDER BY c.conname;
        ");

        $constraintNames = array_column($constraints, 'conname');

        $this->assertContains('ck_book_available_range', $constraintNames);
        $this->assertContains('ck_book_total_positive', $constraintNames);
        $this->assertContains('ck_user_role', $constraintNames);
    }

    /**
     * Verifica que el constraint ck_book_available_range impida existencias negativas o mayores al total.
     */
    public function test_ck_book_available_range_enforces_inventory_limits(): void
    {
        $categoryId = '11111111-1111-4111-8111-111111111111';

        // Intento 1: available_copies negativo (-1)
        $this->expectException(\Illuminate\Database\QueryException::class);
        DB::statement("
            INSERT INTO library.book (id, title, author, isbn, category_id, total_copies, available_copies)
            VALUES ('a0000000-0000-0000-0000-000000000001', 'Test', 'Autor', '9780000000001', '{$categoryId}', 5, -1)
        ");
    }

    /**
     * Verifica que el constraint ck_book_available_range impida disponibles mayores al total.
     */
    public function test_ck_book_available_range_prevents_available_greater_than_total(): void
    {
        $categoryId = '11111111-1111-4111-8111-111111111111';

        $this->expectException(\Illuminate\Database\QueryException::class);
        DB::statement("
            INSERT INTO library.book (id, title, author, isbn, category_id, total_copies, available_copies)
            VALUES ('a0000000-0000-0000-0000-000000000002', 'Test', 'Autor', '9780000000002', '{$categoryId}', 5, 6)
        ");
    }

    /**
     * Verifica los índices parciales requeridos por el spec.
     */
    public function test_required_partial_and_unique_indexes_exist(): void
    {
        $indexes = DB::select("
            SELECT indexname, indexdef
            FROM pg_indexes
            WHERE schemaname = 'library'
            ORDER BY indexname;
        ");

        $indexNames = array_column($indexes, 'indexname');

        $this->assertContains('uq_book_isbn_active', $indexNames);
        $this->assertContains('idx_book_category_title', $indexNames);
        $this->assertContains('uq_loan_item_loan_book', $indexNames);
        $this->assertContains('idx_loan_item_due_unreturned', $indexNames);
    }
}
