<?php

/**
 * Billing Filament Resources Test Suite
 *
 * Tests the InvoiceResource and TransactionResource
 * Filament admin panel resources including:
 * - List page rendering with records
 * - Edit page rendering (Invoice, Transaction)
 * - Navigation group assignment
 * - Table filters
 */

use App\Filament\Resources\Invoices\InvoiceResource;
use App\Filament\Resources\Invoices\Pages\EditInvoice;
use App\Filament\Resources\Invoices\Pages\ListInvoices;
use App\Filament\Resources\Refunds\Pages\ListRefunds;
use App\Filament\Resources\Refunds\RefundResource;
use App\Filament\Resources\Transactions\Pages\EditTransaction;
use App\Filament\Resources\Transactions\Pages\ListTransactions;
use App\Filament\Resources\Transactions\TransactionResource;
use App\Models\Invoice;
use App\Models\Transaction;
use App\Models\User;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->admin = User::where('email', 'admin@demo.com')->first()
        ?? User::factory()->create(['email' => 'admin@demo.com']);
    $this->actingAs($this->admin);
});

// --- Resource configuration tests ---

test('invoice resource is in billing navigation group', function () {
    expect(InvoiceResource::getNavigationGroup())->toBe('Billing');
});

test('transaction resource is in billing navigation group', function () {
    expect(TransactionResource::getNavigationGroup())->toBe('Billing');
});

test('refund resource is in billing navigation group', function () {
    expect(RefundResource::getNavigationGroup())->toBe('Billing');
});

test('refund resource cannot create records', function () {
    expect(RefundResource::canCreate())->toBeFalse();
});

test('refund resource has no create or edit pages', function () {
    $pages = RefundResource::getPages();

    expect($pages)->toHaveKey('index')
        ->and($pages)->not->toHaveKey('create')
        ->and($pages)->not->toHaveKey('edit');
});

// --- Invoice Livewire tests ---

test('invoice list page renders and shows records', function () {
    $invoices = Invoice::factory()->count(3)->create([
        'billable_type' => 'user',
        'billable_id' => $this->admin->id,
    ]);

    livewire(ListInvoices::class)
        ->assertOk()
        ->assertCanSeeTableRecords($invoices);
});

test('invoice edit page renders successfully', function () {
    $invoice = Invoice::factory()->paid()->create([
        'billable_type' => 'user',
        'billable_id' => $this->admin->id,
    ]);

    livewire(EditInvoice::class, [
        'record' => $invoice->getRouteKey(),
    ])->assertOk();
});

test('invoice list page can filter by status', function () {
    Invoice::where('billable_type', 'user')
        ->where('billable_id', $this->admin->id)
        ->delete();

    $paidInvoice = Invoice::factory()->paid()->create([
        'billable_type' => 'user',
        'billable_id' => $this->admin->id,
    ]);

    $openInvoice = Invoice::factory()->open()->create([
        'billable_type' => 'user',
        'billable_id' => $this->admin->id,
    ]);

    livewire(ListInvoices::class)
        ->assertCanSeeTableRecords([$paidInvoice, $openInvoice])
        ->filterTable('status', 'paid')
        ->assertCanSeeTableRecords([$paidInvoice])
        ->assertCanNotSeeTableRecords([$openInvoice]);
});

// --- Transaction Livewire tests ---

test('transaction list page renders and shows records', function () {
    $transactions = Transaction::factory()->count(3)->create([
        'billable_type' => 'user',
        'billable_id' => $this->admin->id,
    ]);

    livewire(ListTransactions::class)
        ->assertOk()
        ->assertCanSeeTableRecords($transactions);
});

test('transaction edit page renders successfully', function () {
    $transaction = Transaction::factory()->succeeded()->create([
        'billable_type' => 'user',
        'billable_id' => $this->admin->id,
    ]);

    livewire(EditTransaction::class, [
        'record' => $transaction->getRouteKey(),
    ])->assertOk();
});

test('transaction list page can filter by status', function () {
    Transaction::where('billable_type', 'user')
        ->where('billable_id', $this->admin->id)
        ->delete();

    $succeededTransaction = Transaction::factory()->succeeded()->create([
        'billable_type' => 'user',
        'billable_id' => $this->admin->id,
    ]);

    $failedTransaction = Transaction::factory()->failed()->create([
        'billable_type' => 'user',
        'billable_id' => $this->admin->id,
    ]);

    livewire(ListTransactions::class)
        ->assertCanSeeTableRecords([$succeededTransaction, $failedTransaction])
        ->filterTable('status', 'succeeded')
        ->assertCanSeeTableRecords([$succeededTransaction])
        ->assertCanNotSeeTableRecords([$failedTransaction]);
});

test('refund list page only shows refunded transactions', function () {
    Transaction::where('billable_type', 'user')
        ->where('billable_id', $this->admin->id)
        ->delete();

    $refunded = Transaction::factory()->refunded()->create([
        'billable_type' => 'user',
        'billable_id' => $this->admin->id,
    ]);

    $partiallyRefunded = Transaction::factory()->create([
        'billable_type' => 'user',
        'billable_id' => $this->admin->id,
        'status' => 'partially_refunded',
        'refunded_amount' => 250,
    ]);

    $succeeded = Transaction::factory()->succeeded()->create([
        'billable_type' => 'user',
        'billable_id' => $this->admin->id,
    ]);

    livewire(ListRefunds::class)
        ->assertOk()
        ->assertCanSeeTableRecords([$refunded, $partiallyRefunded])
        ->assertCanNotSeeTableRecords([$succeeded]);
});
