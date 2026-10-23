<?php

use App\Enums\LeadType;

test('fromInput reads spreadsheet spellings of every type', function (string $input, LeadType $expected) {
    expect(LeadType::fromInput($input))->toBe($expected);
})->with([
    'value' => ['bell', LeadType::Bell],
    'upper case with spaces' => ['  BELL ', LeadType::Bell],
    'insert abbreviation' => ['ins', LeadType::Insert],
    'otp label' => ['OTP', LeadType::Otp],
    'otp code' => ['Otp Code', LeadType::Otp],
    'selection abbreviation' => ['sel', LeadType::Selection],
    'tap code label' => ['Tap Code', LeadType::TapCode],
    'tap code with hyphen' => ['tap-code', LeadType::TapCode],
    'tap code value' => ['tap_code', LeadType::TapCode],
    'tap abbreviation' => ['tap', LeadType::TapCode],
]);

test('fromInput returns null for unknown or blank input', function (?string $input) {
    expect(LeadType::fromInput($input))->toBeNull();
})->with(['unknown word' => ['bellow'], 'blank' => ['   '], 'null' => [null]]);

test('every type has a distinct label', function () {
    expect(collect(LeadType::cases())->map->label()->all())->toBe(['Bell', 'Insert', 'OTP', 'Selection', 'Tap code']);
});
