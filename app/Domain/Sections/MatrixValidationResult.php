<?php

namespace App\Domain\Sections;

final readonly class MatrixValidationResult
{
    /**
     * @param  list<string>  $errors
     */
    public function __construct(private array $errors = []) {}

    public function isValid(): bool
    {
        return $this->errors === [];
    }

    /**
     * @return list<string>
     */
    public function errors(): array
    {
        return $this->errors;
    }
}
