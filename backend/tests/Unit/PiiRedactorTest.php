<?php

declare(strict_types=1);

use App\Services\Ai\PiiRedactor;

/*
|--------------------------------------------------------------------------
| Task 13.6 — PiiRedactor: redact PII before transmission to the model
|--------------------------------------------------------------------------
|
| Supports Requirement 32.3 ("redact personally identifiable information before
| transmission to the language model"). These are pure-logic unit tests: the
| redactor maps rows to redacted rows with no I/O.
|
| Key-based redaction masks a value whose COLUMN name denotes PII; pattern-based
| redaction masks PII-shaped substrings in free-text values; aggregated measures
| (counts, sums, numbers) are preserved so the model still receives useful
| structure.
*/

it('redacts values of PII-named columns while preserving aggregate measures', function () {
    $redactor = new PiiRedactor;

    $rows = [
        ['buyer_name' => 'Acme Traders', 'email' => 'owner@acme.test', 'total_sales' => 12345.67, 'order_count' => 42],
    ];

    $out = $redactor->redactRows($rows);

    expect($out[0]['buyer_name'])->toBe(PiiRedactor::PLACEHOLDER);
    expect($out[0]['email'])->toBe(PiiRedactor::PLACEHOLDER);
    // Aggregated measures are the data the model is allowed to see (R32.3).
    expect($out[0]['total_sales'])->toBe(12345.67);
    expect($out[0]['order_count'])->toBe(42);
});

it('preserves safe catalog/structure dimensions like product_name and category_name', function () {
    $redactor = new PiiRedactor;

    expect($redactor->isPiiKey('product_name'))->toBeFalse();
    expect($redactor->isPiiKey('category_name'))->toBeFalse();
    expect($redactor->isPiiKey('collection_name'))->toBeFalse();
    // But a person's name is PII.
    expect($redactor->isPiiKey('customer_name'))->toBeTrue();
    expect($redactor->isPiiKey('contact_phone'))->toBeTrue();
    expect($redactor->isPiiKey('gstin'))->toBeTrue();
});

it('masks PII-shaped substrings embedded in otherwise non-PII free-text values', function () {
    $redactor = new PiiRedactor;

    $rows = [
        ['note' => 'Call owner at 9876543210 or mail owner@acme.test', 'gst_label' => 'Reg 27AAPFU0939F1ZV done'],
    ];

    $out = $redactor->redactRows($rows);

    expect($out[0]['note'])->not->toContain('9876543210');
    expect($out[0]['note'])->not->toContain('owner@acme.test');
    expect($out[0]['note'])->toContain(PiiRedactor::PLACEHOLDER);
    expect($out[0]['gst_label'])->not->toContain('27AAPFU0939F1ZV');
});

it('leaves a dataset with no PII completely unchanged', function () {
    $redactor = new PiiRedactor;

    $rows = [
        ['product_id' => 1, 'product_name' => 'Widget', 'quantity' => 10.5],
        ['product_id' => 2, 'product_name' => 'Gadget', 'quantity' => 3.0],
    ];

    expect($redactor->redactRows($rows))->toBe($rows);
});
