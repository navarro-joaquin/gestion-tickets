<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Sections\Section;
use App\Http\Controllers\Controller;
use App\Http\Resources\SectionResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SectionController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $sections = Section::query()
            ->where('is_active', true)
            ->with('ticketCodes.categories')
            ->orderBy('sort_order')
            ->orderBy('slug')
            ->get();

        return SectionResource::collection($sections);
    }

    public function show(Section $section): SectionResource
    {
        abort_unless($section->is_active, 404);

        $section->load('ticketCodes.categories');

        return new SectionResource($section);
    }
}
