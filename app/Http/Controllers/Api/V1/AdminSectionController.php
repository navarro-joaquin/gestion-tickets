<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Audit\AuditLogger;
use App\Domain\Sections\Category;
use App\Domain\Sections\Section;
use App\Domain\Sections\SectionMatrixValidator;
use App\Domain\Sections\SectionTicketCode;
use App\Domain\Ticketing\Ticket;
use App\Domain\Ticketing\TicketStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UpdateSectionMatrixRequest;
use App\Http\Requests\Api\V1\UpdateSectionRequest;
use App\Http\Resources\AdminSectionResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AdminSectionController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $sections = Section::query()
            ->withCount('stations')
            ->with('ticketCodes.categories')
            ->orderBy('sort_order')
            ->orderBy('slug')
            ->get();

        return AdminSectionResource::collection($sections);
    }

    public function update(
        UpdateSectionRequest $request,
        Section $section,
        AuditLogger $audit,
    ): JsonResponse {
        $data = $request->validated();
        $data['sort_order'] ??= 0;

        if (! $data['is_active'] && $this->hasActiveTickets($section)) {
            throw ValidationException::withMessages([
                'is_active' => ['No se puede desactivar una sección con tickets en espera o en atención.'],
            ]);
        }

        $before = $this->sectionPayload($section);

        $section->update($data);

        $audit->record('section.updated', $section, $before, $this->sectionPayload($section), $request);

        $section->loadCount('stations')->load('ticketCodes.categories');

        return response()->json([
            'data' => new AdminSectionResource($section),
            'message' => 'Sección actualizada.',
        ]);
    }

    public function updateMatrix(
        UpdateSectionMatrixRequest $request,
        Section $section,
        AuditLogger $audit,
        SectionMatrixValidator $validator,
    ): JsonResponse {
        if ($this->hasActiveTickets($section)) {
            throw ValidationException::withMessages([
                'codes' => ['No se puede editar la matriz mientras haya tickets en espera o en atención.'],
            ]);
        }

        $codes = $request->validated('codes');
        $section->load('ticketCodes.categories');
        $before = $this->matrixPayload($section);

        DB::transaction(function () use ($section, $codes, $validator, $audit, $request, $before): void {
            $existing = $section->ticketCodes->keyBy('code');

            foreach ($codes as $definition) {
                $ticketCode = $existing->get($definition['code']);

                if (! $ticketCode instanceof SectionTicketCode) {
                    throw ValidationException::withMessages([
                        'codes' => ["El código {$definition['code']} no pertenece a la sección {$section->name}."],
                    ]);
                }

                $categoryIds = Category::query()
                    ->whereIn('slug', $definition['categories'])
                    ->pluck('id')
                    ->all();

                $ticketCode->categories()->sync($categoryIds);
            }

            $section->update([
                'matrix_confirmed_at' => now(),
                'matrix_confirmed_by' => $request->user()?->name,
            ]);

            $result = $validator->validate($section->fresh());

            if (! $result->isValid()) {
                throw ValidationException::withMessages(['codes' => $result->errors()]);
            }

            $section->load('ticketCodes.categories');

            $audit->record(
                'section.matrix.updated',
                $section,
                $before,
                $this->matrixPayload($section),
                $request,
            );
        });

        $section->loadCount('stations')->load('ticketCodes.categories');

        return response()->json([
            'data' => new AdminSectionResource($section),
            'message' => 'Matriz de códigos actualizada.',
        ]);
    }

    private function hasActiveTickets(Section $section): bool
    {
        return Ticket::query()
            ->where('current_section_id', $section->id)
            ->whereIn('status', [
                TicketStatus::Waiting,
                TicketStatus::Called,
                TicketStatus::InService,
            ])
            ->exists();
    }

    /**
     * @return array<string, mixed>
     */
    private function sectionPayload(Section $section): array
    {
        return [
            'name' => $section->name,
            'is_active' => $section->is_active,
            'sort_order' => $section->sort_order,
        ];
    }

    /**
     * @return array<string, list<string>>
     */
    private function matrixPayload(Section $section): array
    {
        return $section->ticketCodes
            ->mapWithKeys(fn (SectionTicketCode $code): array => [
                $code->code => $code->categories
                    ->pluck('slug')
                    ->sort()
                    ->values()
                    ->all(),
            ])
            ->all();
    }
}
