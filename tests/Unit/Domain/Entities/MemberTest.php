<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Entities;

use App\Domain\Entities\Member;
use App\Domain\Exceptions\MemberHasOverdueException;
use App\Domain\Exceptions\MemberLimitExceededException;
use App\Domain\Exceptions\ValidationException;
use PHPUnit\Framework\TestCase;

final class MemberTest extends TestCase
{
    private function createMember(): Member
    {
        return new Member(
            id: 'mem-1',
            fullName: 'Carlos Mendoza',
            documentNumber: '1098765432'
        );
    }

    public function test_member_creation_and_attributes(): void
    {
        $member = $this->createMember();

        $this->assertSame('mem-1', $member->id());
        $this->assertSame('Carlos Mendoza', $member->fullName());
        $this->assertSame('1098765432', $member->documentNumber());
    }

    public function test_member_empty_name_or_document_throws_validation_exception(): void
    {
        $this->expectException(ValidationException::class);
        new Member('mem-1', '', '1098765432');
    }

    public function test_can_borrow_succeeds_when_limits_respected_and_no_overdue(): void
    {
        $member = $this->createMember();

        // 1 activo + 2 nuevos = 3 total (límite máximo permitido)
        $member->canBorrow(currentActiveLoansCount: 1, newBooksCount: 2, hasOverdue: false);

        $this->assertTrue(true);
    }

    public function test_can_borrow_fails_when_member_has_overdue_books(): void
    {
        $member = $this->createMember();

        $this->expectException(MemberHasOverdueException::class);
        $this->expectExceptionMessage('tiene libros vencidos pendientes de devolución.');

        $member->canBorrow(currentActiveLoansCount: 1, newBooksCount: 1, hasOverdue: true);
    }

    public function test_can_borrow_fails_when_total_active_books_exceeds_three(): void
    {
        $member = $this->createMember();

        // 2 activos + 2 nuevos = 4 (supera el límite de 3)
        $this->expectException(MemberLimitExceededException::class);
        $this->expectExceptionMessage('El socio supera el límite de 3 libros sin devolver (tiene actualmente 2 libros).');

        $member->canBorrow(currentActiveLoansCount: 2, newBooksCount: 2, hasOverdue: false);
    }
}
