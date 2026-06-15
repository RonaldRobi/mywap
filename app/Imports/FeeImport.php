<?php

namespace App\Imports;

use App\Enums\FeeStatus;
use App\Models\MembershipFee;
use App\Models\Payment;
use App\Models\User;
use App\Services\FeeService;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithStartRow;

class FeeImport implements ToCollection, WithHeadingRow, WithStartRow
{
    protected int $year;
    protected FeeService $feeService;
    protected string $proofPath;
    protected ?int $importBatchId;
    protected ?int $orgId;

    public int $successCount = 0;
    public int $skipCount = 0;
    public array $errors = [];

    public function __construct(int $year, FeeService $feeService, string $proofPath = '', ?int $importBatchId = null, ?int $orgId = null)
    {
        $this->year = $year;
        $this->feeService = $feeService;
        $this->proofPath = $proofPath;
        $this->importBatchId = $importBatchId;
        $this->orgId = $orgId;
    }

    public function startRow(): int
    {
        return 2;
    }

    public function collection(Collection $rows): void
    {
        foreach ($rows as $row) {
            $ic = $this->cleanIc($row['ic_number'] ?? '');
            $memberNo = trim((string) ($row['no_ahli'] ?? ''));

            if (empty($ic) && empty($memberNo)) {
                $this->skipCount++;
                $this->errors[] = "Row {$this->getCurrentRow()}: IC dan No Ahli kosong — dilewati.";
                continue;
            }

            $user = $this->findUser($ic, $memberNo);

            if (! $user) {
                $identity = $ic ?: $memberNo;
                $this->skipCount++;
                $this->errors[] = "Row {$this->getCurrentRow()}: Ahli {$identity} tidak ditemui — dilewati.";
                continue;
            }

            if ($this->feeService->isLifeMember($user) || $this->feeService->isExempted($user)) {
                $this->skipCount++;
                $this->errors[] = "Row {$this->getCurrentRow()}: {$user->name} — status dikecualikan/life member.";
                continue;
            }

            $existingFee = MembershipFee::where('user_id', $user->id)
                ->where('year', $this->year)
                ->first();

            if ($existingFee && $existingFee->status === FeeStatus::Paid) {
                $this->skipCount++;
                $this->errors[] = "Row {$this->getCurrentRow()}: {$user->name} — sudah dibayar untuk {$this->year}.";
                continue;
            }

            $amount = (float) ($row['amount'] ?? $user->organization?->fee_amount ?? 0);
            $reference = trim((string) ($row['reference'] ?? '')) ?: ('CSV-' . strtoupper(Str::random(8)));

            $fee = $this->feeService->markAsPaid($user, $this->year, $amount);

            $payment = Payment::create([
                'user_id' => $user->id,
                'payable_type' => MembershipFee::class,
                'payable_id' => $fee->id,
                'amount' => $amount,
                'status' => 'successful',
                'reference' => $reference,
                'description' => "Yuran keahlian {$user->organization?->name} {$this->year} (Import CSV)",
                'proof_path' => $this->proofPath ?: null,
                'uploaded_by' => auth()->id(),
            ]);

            $fee->update(['payment_id' => $payment->id]);

            $this->successCount++;
        }
    }

    protected function findUser(string $ic, string $memberNo): ?User
    {
        $query = User::withoutGlobalScopes();

        if ($this->orgId) {
            $query->where('current_organization_id', $this->orgId);
        }

        if ($ic && $memberNo) {
            $query->where('ic_number', $ic)
                ->where(function ($q) use ($memberNo) {
                    $q->where('member_no', $memberNo)
                      ->orWhere('original_member_no', $memberNo);
                });
        } elseif ($ic) {
            $query->where('ic_number', $ic);
        } elseif ($memberNo) {
            $query->where(function ($q) use ($memberNo) {
                $q->where('member_no', $memberNo)
                  ->orWhere('original_member_no', $memberNo);
            });
        }

        return $query->first();
    }

    protected function cleanIc(string $ic): string
    {
        return Str::upper(preg_replace('/\s+/', '', trim($ic)));
    }

    protected function getCurrentRow(): int
    {
        return $this->successCount + $this->skipCount + 2;
    }
}
