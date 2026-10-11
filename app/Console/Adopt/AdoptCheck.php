<?php

declare(strict_types=1);

namespace App\Console\Adopt;

use JsonSerializable;

/**
 * One reported assertion of `modules:adopt`. The JSON shape is the repo CLI
 * contract payload (cmd/lib/contract.sh report_result): control, status, message.
 */
final readonly class AdoptCheck implements JsonSerializable
{
    public const PASSED = 'passed';

    public const FAILED = 'failed';

    public const SKIPPED = 'skipped';

    public function __construct(
        public string $control,
        public string $status,
        public string $message = '',
    ) {
    }

    public static function passed(string $control, string $message = ''): self
    {
        return new self($control, self::PASSED, $message);
    }

    public static function failed(string $control, string $message): self
    {
        return new self($control, self::FAILED, $message);
    }

    public static function skipped(string $control, string $message): self
    {
        return new self($control, self::SKIPPED, $message);
    }

    public function failedCheck(): bool
    {
        return $this->status === self::FAILED;
    }

    /** @return array{control: string, status: string, message: string} */
    public function jsonSerialize(): array
    {
        return [
            'control' => $this->control,
            'status' => $this->status,
            'message' => $this->message,
        ];
    }
}
