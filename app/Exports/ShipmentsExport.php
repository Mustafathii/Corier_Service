<?php

namespace App\Exports;

use App\Models\Shipment;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class ShipmentsExport implements FromCollection, WithMapping, WithHeadings, ShouldAutoSize, WithStyles, WithColumnWidths
{
    protected $shipments;

    public function __construct($shipments)
    {
        $this->shipments = $shipments;
    }

    public function collection()
    {
        return $this->shipments;
    }

    public function headings(): array
    {
        return [
            'Tracking Number',
            'Status',
            'Sender Name',
            'Seller Company',
            'Receiver Name',
            'Receiver Phone', // جديد هنا - أصبح العمود F
            'Receiver City',  // أصبح العمود G
            'Shipment Type',  // أصبح العمود H
            'Weight (kg)',    // أصبح العمود I
            'Shipping Cost',  // أصبح العمود J
            'Payment Method', // أصبح العمود K
            'COD Amount',     // أصبح العمود L
            'Driver',         // أصبح العمود M
            'Expected Delivery Date', // أصبح العمود N
            'Actual Delivery Date', // أصبح العمود O
            'Pickup Date',    // أصبح العمود P
            'Created At',     // أصبح العمود Q
        ];
    }

    public function map($shipment): array
    {
        return [
            $shipment->tracking_number,
            ucfirst(str_replace('_', ' ', $shipment->status)),
            $shipment->sender_name,
            $shipment->seller->company_name ?? 'Individual',
            $shipment->receiver_name,
            $shipment->receiver_phone, // تأكد أنه هنا
            $shipment->receiver_city,
            ucfirst(str_replace('_', ' ', $shipment->shipment_type)),
            $shipment->weight,
            $shipment->shipping_cost,
            ucfirst(str_replace('_', ' ', $shipment->payment_method)),
            $shipment->cod_amount,
            $shipment->driver->name ?? 'Unassigned',
            $shipment->expected_delivery_date ? $shipment->expected_delivery_date->format('Y-m-d') : null,
            $shipment->actual_delivery_date ? $shipment->actual_delivery_date->format('Y-m-d H:i:s') : null,
            $shipment->pickup_date ? $shipment->pickup_date->format('Y-m-d H:i:s') : null,
            $shipment->created_at ? $shipment->created_at->format('Y-m-d H:i:s') : null,
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->getStyle(1)->applyFromArray([
            'font' => [
                'bold' => true,
                'size' => 12,
                'color' => ['argb' => Color::COLOR_WHITE],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['argb' => 'FF4F81BD'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);

        $sheet->freezePane('A2');

        foreach ($sheet->getRowIterator() as $row) {
            $rowIndex = $row->getRowIndex();
            if ($rowIndex === 1) {
                continue;
            }

            $sheet->getStyle($rowIndex)->applyFromArray([
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
            ]);

            if ($rowIndex % 2 === 0) {
                $sheet->getStyle($rowIndex)->applyFromArray([
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['argb' => 'FFE0EBF5'],
                    ],
                ]);
            }
        }
    }

    public function columnWidths(): array
    {
        return [
            'A' => 20, // Tracking Number
            'B' => 15, // Status
            'C' => 25, // Sender Name
            'D' => 30, // Seller Company
            'E' => 25, // Receiver Name
            'F' => 20, // Receiver Phone    <-- هذا هو التعديل الجديد لـ receiver_phone
            'G' => 15, // Receiver City     <-- هذا الآن هو العمود G بدلاً من F
            'H' => 12, // Shipment Type     <-- أصبح H
            'I' => 15, // Weight (kg)       <-- أصبح I
            'J' => 20, // Shipping Cost     <-- أصبح J
            'K' => 15, // Payment Method    <-- أصبح K
            'L' => 20, // COD Amount        <-- أصبح L
            'M' => 20, // Driver            <-- أصبح M
            'N' => 22, // Expected Delivery Date <-- أصبح N
            'O' => 22, // Actual Delivery Date <-- أصبح O
            'P' => 22, // Pickup Date       <-- أصبح P
            'Q' => 22, // Created At        <-- أصبح Q
        ];
    }
}