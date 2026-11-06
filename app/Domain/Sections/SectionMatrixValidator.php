<?php

namespace App\Domain\Sections;

class SectionMatrixValidator
{
    /**
     * @var list<string>
     */
    private const REQUIRED_CATEGORIES = [
        'general',
        'pregnancy',
        'elderly',
        'disability',
    ];

    /**
     * @var array<string, string>
     */
    private const CATEGORY_LABELS = [
        'general' => 'general',
        'pregnancy' => 'embarazo',
        'elderly' => 'tercera edad',
        'disability' => 'discapacidad',
    ];

    /**
     * @var list<array<int, string>>
     */
    private const ALLOWED_CODE_CATEGORY_SETS = [
        ['general'],
        ['pregnancy'],
        ['disability', 'elderly'],
    ];

    public function validate(Section $section): MatrixValidationResult
    {
        $section->load('ticketCodes.categories');

        $codes = $section->ticketCodes
            ->where('is_active', true)
            ->values();

        if ($codes->count() !== 3) {
            return new MatrixValidationResult([
                "La sección {$section->name} debe tener exactamente 3 códigos activos; tiene {$codes->count()}.",
            ]);
        }

        $errors = [];

        foreach ($codes as $ticketCode) {
            $categorySlugs = $ticketCode->categories
                ->pluck('slug')
                ->sort()
                ->values()
                ->all();

            if (! in_array($categorySlugs, self::ALLOWED_CODE_CATEGORY_SETS, true)) {
                $categories = $categorySlugs === []
                    ? 'ninguna'
                    : implode(', ', $categorySlugs);

                $errors[] = "El código {$ticketCode->code} de {$section->name} tiene una combinación de categorías no permitida: {$categories}.";
            }
        }

        /** @var array<string, list<string>> $codesByCategory */
        $codesByCategory = [];

        foreach ($codes as $ticketCode) {
            foreach ($ticketCode->categories as $category) {
                $codesByCategory[$category->slug][] = $ticketCode->code;
            }
        }

        foreach (self::REQUIRED_CATEGORIES as $categorySlug) {
            $matchingCodes = $codesByCategory[$categorySlug] ?? [];
            $label = self::CATEGORY_LABELS[$categorySlug];

            if (count($matchingCodes) !== 1) {
                $errors[] = "La categoría {$label} de {$section->name} debe estar asignada a exactamente un código; está asignada a ".count($matchingCodes).'.';
            }
        }

        return new MatrixValidationResult($errors);
    }
}
