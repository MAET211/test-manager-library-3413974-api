<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Repositories;

use App\Application\Ports\Outbound\LoanRepositoryInterface;
use App\Domain\Entities\Loan;
use App\Domain\Entities\LoanItem;
use App\Infrastructure\Persistence\Eloquent\Models\LoanItemModel;
use App\Infrastructure\Persistence\Eloquent\Models\LoanModel;
use App\Infrastructure\Persistence\Mappers\LoanItemMapper;
use App\Infrastructure\Persistence\Mappers\LoanMapper;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Facades\DB;

final class EloquentLoanRepository implements LoanRepositoryInterface
{
    public function save(Loan $loan): void
    {
        DB::transaction(function () use ($loan) {
            $loanModel = LoanModel::find($loan->id());
            $loanModel = LoanMapper::toModel($loan, $loanModel);
            $loanModel->save();

            foreach ($loan->items() as $item) {
                $itemModel = LoanItemModel::find($item->id());
                $itemModel = LoanItemMapper::toModel($item, $itemModel);
                $itemModel->save();
            }
        });
    }

    public function findById(string $id): ?Loan
    {
        $model = LoanModel::with('items')->find($id);

        return $model !== null ? LoanMapper::toDomain($model) : null;
    }

    public function list(
        ?DateTimeImmutable $from,
        ?DateTimeImmutable $to,
        ?string $status,
        ?string $memberId,
        int $page = 1,
        int $size = 20
    ): array {
        $builder = LoanModel::with('items');

        if ($memberId !== null && trim($memberId) !== '') {
            $builder->where('member_id', $memberId);
        }

        if ($from !== null) {
            $builder->where('loaned_at', '>=', $from->format('Y-m-d H:i:sP'));
        }

        if ($to !== null) {
            $builder->where('loaned_at', '<=', $to->format('Y-m-d H:i:sP'));
        }

        $allModels = $builder->orderBy('loaned_at', 'desc')->get();
        $domainLoans = $allModels->map(fn (LoanModel $m) => LoanMapper::toDomain($m));

        if ($status !== null && trim($status) !== '') {
            $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
            $domainLoans = $domainLoans->filter(fn (Loan $l) => $l->status($now)->value === $status)->values();
        }

        $total = $domainLoans->count();
        $offset = ($page - 1) * $size;
        $items = $domainLoans->slice($offset, $size)->values()->all();

        return [
            'items' => $items,
            'total' => $total,
        ];
    }

    public function findUnreturnedItemsByMemberId(string $memberId): array
    {
        $models = LoanItemModel::whereNull('returned_at')
            ->whereHas('loan', fn ($q) => $q->where('member_id', $memberId))
            ->get();

        return $models->map(fn (LoanItemModel $m) => LoanItemMapper::toDomain($m))->all();
    }

    public function findOverdueItems(?DateTimeImmutable $asOf = null): array
    {
        $now = $asOf ?? new DateTimeImmutable('now', new DateTimeZone('UTC'));

        $models = LoanItemModel::with('loan.member')
            ->whereNull('returned_at')
            ->where('due_at', '<', $now->format('Y-m-d H:i:sP'))
            ->get();

        $result = [];
        foreach ($models as $m) {
            $dueAt = DateTimeImmutable::createFromInterface($m->due_at)->setTimezone(new DateTimeZone('UTC'));
            $daysLate = (int) $now->diff($dueAt)->days;

            $result[] = [
                'loan_id' => (string) $m->loan_id,
                'item_id' => (string) $m->id,
                'book_title' => (string) $m->book_title,
                'member_name' => (string) ($m->loan?->member?->full_name ?? 'Socio'),
                'due_at' => $dueAt,
                'days_late' => $daysLate,
            ];
        }

        return $result;
    }
}
