<?php

namespace App\Domain\Sections;

class SectionActivationGate
{
    public function __construct(private readonly SectionMatrixValidator $validator) {}

    public function isOperational(Section $section): bool
    {
        if (! $section->is_active || ! $section->isMatrixConfirmed()) {
            return false;
        }

        return $this->validator->validate($section)->isValid();
    }
}
