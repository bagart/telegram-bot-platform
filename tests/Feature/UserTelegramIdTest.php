<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;

describe('users.telegram_id', function () {
    it('exists on the users table', function () {
        expect(Schema::hasColumn('users', 'telegram_id'))->toBeTrue();
    });

    it('persists a telegram id', function () {
        $user = User::factory()->create(['telegram_id' => 987654321]);

        expect(User::query()->findOrFail($user->id)->telegram_id)->toBe(987654321);
    });

    it('rejects duplicate telegram ids', function () {
        User::factory()->create(['telegram_id' => 987654321]);

        expect(fn (): mixed => User::factory()->create(['telegram_id' => 987654321]))
            ->toThrow(QueryException::class);
    });

    it('supports telegram-first accounts without email or password', function () {
        $user = User::query()->create([
            'name' => 'tg_111',
            'telegram_id' => 111222333,
        ]);

        expect($user->email)->toBeNull()
            ->and($user->password)->toBeNull()
            ->and(User::query()->findOrFail($user->id)->telegram_id)->toBe(111222333);
    });
});
