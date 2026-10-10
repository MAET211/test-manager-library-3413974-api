<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Members;

use App\Application\Services\Members\CreateMemberService;
use App\Application\Services\Members\GetMemberService;
use App\Application\Services\Members\SearchMembersService;
use App\Domain\Entities\Loan;
use App\Domain\Entities\LoanItem;
use App\Domain\Entities\Member;
use App\Domain\Exceptions\DocumentTakenException;
use App\Domain\Exceptions\ResourceNotFoundException;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use Tests\Fakes\InMemoryLoanRepository;
use Tests\Fakes\InMemoryMemberRepository;

final class MembersServicesTest extends TestCase
{
    private InMemoryMemberRepository $memberRepository;
    private InMemoryLoanRepository $loanRepository;
    private CreateMemberService $createMemberService;
    private SearchMembersService $searchMembersService;
    private GetMemberService $getMemberService;

    protected function setUp(): void
    {
        $this->memberRepository = new InMemoryMemberRepository();
        $this->loanRepository = new InMemoryLoanRepository();

        $this->createMemberService = new CreateMemberService($this->memberRepository);
        $this->searchMembersService = new SearchMembersService($this->memberRepository);
        $this->getMemberService = new GetMemberService($this->memberRepository, $this->loanRepository);
    }

    public function test_create_member_succeeds(): void
    {
        $member = $this->createMemberService->execute('m-1', 'Laura Gómez', '1020304050');

        $this->assertSame('m-1', $member->id());
        $this->assertSame('Laura Gómez', $member->fullName());
        $this->assertSame('1020304050', $member->documentNumber());

        $saved = $this->memberRepository->findById('m-1');
        $this->assertNotNull($saved);
    }

    public function test_create_member_fails_when_document_number_already_registered(): void
    {
        $this->createMemberService->execute('m-1', 'Laura Gómez', '1020304050');

        $this->expectException(DocumentTakenException::class);
        $this->expectExceptionMessage('El documento de identidad «1020304050» ya se encuentra registrado.');

        $this->createMemberService->execute('m-2', 'Otro Socio', '1020304050');
    }

    public function test_search_members_by_name_or_document(): void
    {
        $this->memberRepository->save(new Member('m-1', 'Laura Gómez', '1020304050'));
        $this->memberRepository->save(new Member('m-2', 'Carlos Mario', '9988776655'));

        $byName = $this->searchMembersService->execute(query: 'laura');
        $this->assertSame(1, $byName['total']);
        $this->assertSame('m-1', $byName['items'][0]->id());

        $byDoc = $this->searchMembersService->execute(query: '998877');
        $this->assertSame(1, $byDoc['total']);
        $this->assertSame('m-2', $byDoc['items'][0]->id());
    }

    public function test_get_member_returns_member_with_active_loans_count(): void
    {
        $member = new Member('m-1', 'Laura Gómez', '1020304050');
        $this->memberRepository->save($member);

        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $item1 = new LoanItem('it-1', 'l-1', 'b-1', 'Libro 1', '9780000000001', 'Novela', $now->modify('+14 days'));
        $item2 = new LoanItem('it-2', 'l-1', 'b-2', 'Libro 2', '9780000000002', 'Novela', $now->modify('+14 days'));
        $loan = new Loan('l-1', 'm-1', 'u-1', $now, [$item1, $item2]);
        $this->loanRepository->save($loan);

        $result = $this->getMemberService->execute('m-1');

        $this->assertSame('m-1', $result['member']->id());
        $this->assertSame(2, $result['active_loans']);
    }

    public function test_get_member_fails_when_not_found(): void
    {
        $this->expectException(ResourceNotFoundException::class);
        $this->expectExceptionMessage('El socio con ID «no-existe» no existe.');

        $this->getMemberService->execute('no-existe');
    }
}
