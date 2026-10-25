<?php

namespace Tests\Feature;

use App\Models\{AuditLog, Category, Division, Item, Loan, LoanCorrection, LoanExtension, LoanItem, ReturnClaim, ReturnClaimLine, Setting, StockMovement, User};
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ModelSkemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_fillable_cocok_dengan_kolom_tabel(): void
    {
        $models = [AuditLog::class, Category::class, Division::class, Item::class, Loan::class,
            LoanCorrection::class, LoanExtension::class, LoanItem::class, ReturnClaim::class,
            ReturnClaimLine::class, Setting::class, StockMovement::class, User::class];

        foreach ($models as $class) {
            $m = new $class;
            $this->assertTrue(
                Schema::hasColumns($m->getTable(), $m->getFillable()),
                "Fillable {$class} tidak cocok dengan tabel {$m->getTable()}"
            );
        }
    }

    public static function relasi(): array
    {
        return [
            [Category::class, 'items', HasMany::class, Item::class, 'kategori_id'],
            [Division::class, 'loans', HasMany::class, Loan::class, 'division_id'],
            [Item::class, 'kategori', BelongsTo::class, Category::class, 'kategori_id'],
            [Item::class, 'loanItems', HasMany::class, LoanItem::class, 'item_id'],
            [Item::class, 'stockMovements', HasMany::class, StockMovement::class, 'item_id'],
            [Loan::class, 'division', BelongsTo::class, Division::class, 'division_id'],
            [Loan::class, 'items', HasMany::class, LoanItem::class, 'loan_id'],
            [Loan::class, 'klaim', HasMany::class, ReturnClaim::class, 'loan_id'],
            [Loan::class, 'perpanjangan', HasMany::class, LoanExtension::class, 'loan_id'],
            [Loan::class, 'koreksi', HasMany::class, LoanCorrection::class, 'loan_id'],
            [Loan::class, 'dibatalkanOleh', BelongsTo::class, User::class, 'dibatalkan_oleh'],
            [LoanItem::class, 'loan', BelongsTo::class, Loan::class, 'loan_id'],
            [LoanItem::class, 'item', BelongsTo::class, Item::class, 'item_id'],
            [LoanItem::class, 'barisKlaim', HasMany::class, ReturnClaimLine::class, 'loan_item_id'],
            [LoanCorrection::class, 'loan', BelongsTo::class, Loan::class, 'loan_id'],
            [LoanCorrection::class, 'admin', BelongsTo::class, User::class, 'user_id'],
            [LoanExtension::class, 'loan', BelongsTo::class, Loan::class, 'loan_id'],
            [LoanExtension::class, 'admin', BelongsTo::class, User::class, 'user_id'],
            [ReturnClaim::class, 'loan', BelongsTo::class, Loan::class, 'loan_id'],
            [ReturnClaim::class, 'baris', HasMany::class, ReturnClaimLine::class, 'claim_id'],
            [ReturnClaim::class, 'verifikator', BelongsTo::class, User::class, 'diverifikasi_oleh'],
            [ReturnClaimLine::class, 'klaim', BelongsTo::class, ReturnClaim::class, 'claim_id'],
            [ReturnClaimLine::class, 'loanItem', BelongsTo::class, LoanItem::class, 'loan_item_id'],
            [StockMovement::class, 'item', BelongsTo::class, Item::class, 'item_id'],
            [StockMovement::class, 'admin', BelongsTo::class, User::class, 'user_id'],
            [AuditLog::class, 'admin', BelongsTo::class, User::class, 'user_id'],
        ];
    }

    #[DataProvider('relasi')]
    public function test_relasi(string $model, string $nama, string $tipe, string $terkait, string $fk): void
    {
        $relasi = (new $model)->$nama();

        $this->assertInstanceOf($tipe, $relasi);
        $this->assertInstanceOf($terkait, $relasi->getRelated());
        $this->assertSame($fk, $relasi->getForeignKeyName());
    }
}