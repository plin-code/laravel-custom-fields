<?php

declare(strict_types=1);

use PlinCode\CustomFields\Facades\CustomFields;
use PlinCode\CustomFields\Models\CustomField;
use Workbench\App\Models\Article;

beforeEach(function (): void {
    CustomFields::registerEntity(Article::class, 'article');

    CustomField::create(['name' => 'Rank', 'type' => 'number', 'entity_type' => 'article']);
    CustomField::create(['name' => 'Tags', 'type' => 'multiselect', 'entity_type' => 'article', 'options' => [
        ['key' => 'a', 'label' => 'A', 'is_active' => true],
    ]]);
});

it('refuses an empty string', function (): void {
    expect(fn () => Article::query()->whereCustomField('rank', ''))
        ->toThrow(InvalidArgumentException::class, 'Custom field [rank] cannot be compared with an empty string.');
});

it('matches the hosts without a value when the value is null', function (): void {
    Article::create(['title' => 'with'])->setCustomFields(['rank' => 1]);
    Article::create(['title' => 'other field'])->setCustomFields(['tags' => ['a']]);
    Article::create(['title' => 'without']);

    expect(Article::query()->whereCustomField('rank', null)->orderBy('title')->pluck('title')->all())
        ->toBe(['other field', 'without']);
});

it('matches the hosts without a value on a multiselect when the value is null', function (): void {
    Article::create(['title' => 'with'])->setCustomFields(['tags' => ['a']]);
    Article::create(['title' => 'without']);

    expect(Article::query()->whereCustomField('tags', null)->pluck('title')->all())->toBe(['without']);
});

it('still matches a stored value', function (): void {
    Article::create(['title' => 'one'])->setCustomFields(['rank' => 1]);
    Article::create(['title' => 'two'])->setCustomFields(['rank' => 2]);
    Article::create(['title' => 'tagged'])->setCustomFields(['tags' => ['a']]);

    expect(Article::query()->whereCustomField('rank', 1)->pluck('title')->all())->toBe(['one'])
        ->and(Article::query()->whereCustomField('tags', 'a')->pluck('title')->all())->toBe(['tagged']);
});
