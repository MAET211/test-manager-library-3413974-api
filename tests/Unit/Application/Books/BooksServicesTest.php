<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Books;

use App\Application\Services\Books\CreateBookService;
use App\Application\Services\Books\DeleteBookService;
use App\Application\Services\Books\DeleteCoverService;
use App\Application\Services\Books\GetBookService;
use App\Application\Services\Books\SearchBooksService;
use App\Application\Services\Books\UpdateBookService;
use App\Application\Services\Books\UploadCoverService;
use App\Domain\Entities\Book;
use App\Domain\Exceptions\BookHasOpenLoansException;
use App\Domain\Exceptions\IsbnTakenException;
use App\Domain\Exceptions\ResourceNotFoundException;
use App\Domain\Exceptions\ValidationException;
use App\Domain\ValueObjects\Isbn;
use PHPUnit\Framework\TestCase;
use Tests\Fakes\FakeCoverStorage;
use Tests\Fakes\InMemoryBookRepository;
use Tests\Fakes\InMemoryCategoryRepository;

final class BooksServicesTest extends TestCase
{
    private InMemoryBookRepository $bookRepository;
    private InMemoryCategoryRepository $categoryRepository;
    private FakeCoverStorage $coverStorage;
    private CreateBookService $createBookService;
    private UpdateBookService $updateBookService;
    private DeleteBookService $deleteBookService;
    private SearchBooksService $searchBooksService;
    private GetBookService $getBookService;
    private UploadCoverService $uploadCoverService;
    private DeleteCoverService $deleteCoverService;

    private string $categoryId = '11111111-1111-4111-8111-111111111111';

    protected function setUp(): void
    {
        $this->bookRepository = new InMemoryBookRepository();
        $this->categoryRepository = new InMemoryCategoryRepository();
        $this->coverStorage = new FakeCoverStorage();

        $this->createBookService = new CreateBookService($this->bookRepository, $this->categoryRepository);
        $this->updateBookService = new UpdateBookService($this->bookRepository, $this->categoryRepository);
        $this->deleteBookService = new DeleteBookService($this->bookRepository);
        $this->searchBooksService = new SearchBooksService($this->bookRepository);
        $this->getBookService = new GetBookService($this->bookRepository);
        $this->uploadCoverService = new UploadCoverService($this->bookRepository, $this->coverStorage);
        $this->deleteCoverService = new DeleteCoverService($this->bookRepository, $this->coverStorage);
    }

    public function test_create_book_succeeds_and_available_copies_equal_total(): void
    {
        $book = $this->createBookService->execute(
            id: 'b-1',
            title: 'Rayuela',
            author: 'Julio Cortázar',
            isbn: '9780307474728',
            categoryId: $this->categoryId,
            totalCopies: 4
        );

        $this->assertSame('b-1', $book->id());
        $this->assertSame(4, $book->totalCopies());
        $this->assertSame(4, $book->availableCopies());
        $this->assertSame('9780307474728', $book->isbn()->value());
    }

    public function test_create_book_fails_if_isbn_already_registered_in_active_book(): void
    {
        $this->createBookService->execute(
            id: 'b-1',
            title: 'Libro 1',
            author: 'Autor',
            isbn: '9780307474728',
            categoryId: $this->categoryId,
            totalCopies: 2
        );

        $this->expectException(IsbnTakenException::class);
        $this->expectExceptionMessage('El ISBN «9780307474728» ya está registrado en otro libro activo.');

        $this->createBookService->execute(
            id: 'b-2',
            title: 'Libro 2',
            author: 'Autor 2',
            isbn: '9780307474728',
            categoryId: $this->categoryId,
            totalCopies: 3
        );
    }

    public function test_update_book_succeeds_and_adjusts_available_copies(): void
    {
        $book = new Book(
            id: 'b-1',
            title: 'Título Original',
            author: 'Autor Original',
            isbn: new Isbn('9780307474728'),
            categoryId: $this->categoryId,
            totalCopies: 3,
            availableCopies: 2 // 1 prestado
        );
        $this->bookRepository->save($book);

        // Aumentar total a 5 (+2). Disponibles deben subir a 4 (+2)
        $updated = $this->updateBookService->execute(
            id: 'b-1',
            title: 'Título Editado',
            author: 'Autor Editado',
            categoryId: $this->categoryId,
            totalCopies: 5
        );

        $this->assertSame('Título Editado', $updated->title());
        $this->assertSame(5, $updated->totalCopies());
        $this->assertSame(4, $updated->availableCopies());
    }

    public function test_delete_book_soft_deletes_when_no_active_loans(): void
    {
        $book = new Book(
            id: 'b-1',
            title: 'Para Borrar',
            author: 'Autor',
            isbn: new Isbn('9780307474728'),
            categoryId: $this->categoryId,
            totalCopies: 2
        );
        $this->bookRepository->save($book);
        $this->bookRepository->setActiveLoans('b-1', false);

        $this->deleteBookService->execute('b-1');

        $saved = $this->bookRepository->findById('b-1');
        $this->assertTrue($saved->isDeleted());
    }

    public function test_delete_book_fails_if_book_has_open_loans(): void
    {
        $book = new Book(
            id: 'b-1',
            title: 'Con Préstamos',
            author: 'Autor',
            isbn: new Isbn('9780307474728'),
            categoryId: $this->categoryId,
            totalCopies: 2
        );
        $this->bookRepository->save($book);
        $this->bookRepository->setActiveLoans('b-1', true);

        $this->expectException(BookHasOpenLoansException::class);
        $this->expectExceptionMessage('No se puede dar de baja el libro «Con Préstamos» porque tiene préstamos abiertos.');

        $this->deleteBookService->execute('b-1');
    }

    public function test_search_books_returns_only_active_books(): void
    {
        $b1 = new Book('b-1', 'Cien años', 'García', new Isbn('9780000000001'), $this->categoryId, 2);
        $b2 = new Book('b-2', 'El coronel', 'García', new Isbn('9780000000002'), $this->categoryId, 2);
        $b3 = new Book('b-3', 'Borrado', 'García', new Isbn('9780000000003'), $this->categoryId, 2);
        $b3->softDelete(false);

        $this->bookRepository->save($b1);
        $this->bookRepository->save($b2);
        $this->bookRepository->save($b3);

        $result = $this->searchBooksService->execute(query: 'García');

        $this->assertSame(2, $result['total']);
        $this->assertCount(2, $result['items']);
    }

    public function test_upload_cover_and_delete_cover(): void
    {
        $book = new Book('b-1', 'Libro', 'Autor', new Isbn('9780000000001'), $this->categoryId, 2);
        $this->bookRepository->save($book);

        // 1. Subir portada válida
        $coverKey = $this->uploadCoverService->execute('b-1', 'fake_bytes_jpg', 'image/jpeg');
        $this->assertNotNull($coverKey);
        $this->assertTrue($this->coverStorage->has($coverKey));

        $savedBook = $this->bookRepository->findById('b-1');
        $this->assertSame($coverKey, $savedBook->coverKey());

        // 2. Eliminar portada (primero BD, luego storage)
        $this->deleteCoverService->execute('b-1');

        $afterDelete = $this->bookRepository->findById('b-1');
        $this->assertNull($afterDelete->coverKey());
        $this->assertFalse($this->coverStorage->has($coverKey));
    }

    public function test_upload_cover_fails_with_invalid_mime(): void
    {
        $book = new Book('b-1', 'Libro', 'Autor', new Isbn('9780000000001'), $this->categoryId, 2);
        $this->bookRepository->save($book);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Formato de imagen inválido. Solo se admiten imágenes JPG o PNG.');

        $this->uploadCoverService->execute('b-1', 'bytes', 'application/pdf');
    }
}
