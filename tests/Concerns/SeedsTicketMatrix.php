<?php

namespace Tests\Concerns;

use App\Domain\Kiosks\KioskDevice;
use App\Domain\Sections\Category;
use App\Domain\Sections\Section;
use Database\Seeders\CategorySeeder;
use Database\Seeders\PriorityProfileSeeder;
use Database\Seeders\SectionSeeder;
use Database\Seeders\StationSeeder;

trait SeedsTicketMatrix
{
    protected function seedMatrix(): void
    {
        $this->seed([
            CategorySeeder::class,
            SectionSeeder::class,
            StationSeeder::class,
            PriorityProfileSeeder::class,
        ]);
    }

    protected function section(string $slug): Section
    {
        return Section::query()->where('slug', $slug)->sole();
    }

    protected function category(string $slug): Category
    {
        return Category::query()->where('slug', $slug)->sole();
    }

    protected function kioskFor(string $sectionSlug): KioskDevice
    {
        $kiosk = KioskDevice::factory()->create();
        $kiosk->sections()->attach($this->section($sectionSlug)->id);

        return $kiosk;
    }
}
