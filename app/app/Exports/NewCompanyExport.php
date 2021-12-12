<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

class NewCompanyExport implements FromArray, WithHeadings, ShouldAutoSize
{

    protected $data;

    public function __construct($data)
    {
        $this->data = $data;
    }

    /**
     * @return array
     */
    public function array(): array
    {
        return $this->data;
    }

    public function headings(): array
    {
        return [
            '№',
            'Свойство',
            'Значение',
            'Дополнительно',
        ];
    }
}
