<?php

namespace App\Http\Requests\Api\V1;

class StoreReportExportRequest extends ReportFilterRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $rules = parent::rules();
        unset($rules['per_page']);

        return $rules;
    }
}
