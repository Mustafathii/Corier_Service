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
        // تحميل الـ relationships الأساسية فقط عشان نتجنب الأخطاء
        return $this->shipments->load(['seller', 'driver']);
    }

    public function headings(): array
    {
        return [
            'Tracking Number',
            'Status',
            'Sender Name',
            'Sender Phone',
            'Sender Address',
            'Sender City',
            'Seller Company',
            'Receiver Name',
            'Receiver Phone',
            'Receiver Address',
            'Receiver City',
            'Governorate',
            'City',
            'Shipment Type',
            'Weight (kg)',
            'Shipping Cost',
            'Payment Method',
            'COD Amount',
            'Package Type',
            'Quantity',
            'Declared Value',
            'Description',
            'Driver',
            'Expected Delivery Date',
            'Actual Delivery Date',
            'Pickup Date',
            'Notes',
            'Created At',
        ];
    }

    public function map($shipment): array
    {
        return [
            $shipment->tracking_number,
            $this->formatStatus($shipment->status),
            $shipment->sender_name ?: '-',
            $shipment->sender_phone ?: '-',
            $shipment->sender_address ?: '-',
            $shipment->sender_city ?: '-',
            $shipment->seller?->company_name ?: 'Individual',
            $shipment->receiver_name,
            $shipment->receiver_phone,
            $shipment->receiver_address,
            $shipment->receiver_city,

            // استخدام الـ IDs لحد ما نتأكد من الـ relationships
            $this->getGovernorateNameSafely($shipment),
            $this->getCityNameSafely($shipment),

            $this->formatShipmentType($shipment->shipment_type),
            $shipment->weight ?: '-',
            $shipment->shipping_cost ? number_format($shipment->shipping_cost, 2) : '0.00',
            $this->formatPaymentMethod($shipment->payment_method),
            $shipment->cod_amount ? number_format($shipment->cod_amount, 2) : '0.00',
            $shipment->package_type ?: '-',
            $shipment->quantity ?: '1',
            $shipment->declared_value ? number_format($shipment->declared_value, 2) : '0.00',
            $shipment->description ?: '-',
            $shipment->driver?->name ?: 'Unassigned',
            $shipment->expected_delivery_date ? $shipment->expected_delivery_date->format('Y-m-d') : '-',
            $shipment->actual_delivery_date ? $shipment->actual_delivery_date->format('Y-m-d H:i:s') : '-',
            $shipment->pickup_date ? $shipment->pickup_date->format('Y-m-d H:i:s') : '-',
            $shipment->notes ?: '-',
            $shipment->created_at ? $shipment->created_at->format('Y-m-d H:i:s') : '-',
        ];
    }

    /**
     * الحصول على اسم المحافظة بأمان
     */
    private function getGovernorateNameSafely($shipment)
    {
        try {
            if ($shipment->governorate_id && $shipment->governorate) {
                return $shipment->governorate->governorate_name_en ?: $shipment->governorate->governorate_name_ar;
            }
            return $shipment->governorate_id ? "Gov ID: {$shipment->governorate_id}" : '-';
        } catch (\Exception $e) {
            return $shipment->governorate_id ? "Gov ID: {$shipment->governorate_id}" : '-';
        }
    }

    /**
     * الحصول على اسم المدينة بأمان
     */
    private function getCityNameSafely($shipment)
    {
        try {
            if ($shipment->city_id && $shipment->city) {
                return $shipment->city->city_name_en ?: $shipment->city->city_name_ar;
            }
            return $shipment->city_id ? "City ID: {$shipment->city_id}" : '-';
        } catch (\Exception $e) {
            return $shipment->city_id ? "City ID: {$shipment->city_id}" : '-';
        }
    }

    /**
     * تنسيق حالة الشحنة
     */
    private function formatStatus($status)
    {
        $statuses = [
            'pending' => 'Pending',
            'picked_up' => 'Picked Up',
            'in_transit' => 'In Transit',
            'out_for_delivery' => 'Out for Delivery',
            'delivered' => 'Delivered',
            'failed_delivery' => 'Failed Delivery',
            'returned' => 'Returned',
            'canceled' => 'Canceled',
        ];

        return $statuses[$status] ?? ucfirst(str_replace('_', ' ', $status ?: 'unknown'));
    }

    /**
     * تنسيق نوع الشحنة
     */
    private function formatShipmentType($type)
    {
        $types = [
            'standard' => 'Standard',
            'express' => 'Express',
            'same_day' => 'Same Day',
        ];

        return $types[$type] ?? ucfirst(str_replace('_', ' ', $type ?: 'standard'));
    }

    /**
     * تنسيق طريقة الدفع
     */
    private function formatPaymentMethod($method)
    {
        $methods = [
            'cod' => 'Cash on Delivery',
            'prepaid' => 'Prepaid',
            'electronic_wallet' => 'Electronic Wallet',
        ];

        return $methods[$method] ?? ucfirst(str_replace('_', ' ', $method ?: 'cod'));
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
            'C' => 20, // Sender Name
            'D' => 15, // Sender Phone
            'E' => 25, // Sender Address
            'F' => 15, // Sender City
            'G' => 25, // Seller Company
            'H' => 20, // Receiver Name
            'I' => 15, // Receiver Phone
            'J' => 25, // Receiver Address
            'K' => 15, // Receiver City
            'L' => 15, // Governorate
            'M' => 15, // City
            'N' => 12, // Shipment Type
            'O' => 10, // Weight
            'P' => 12, // Shipping Cost
            'Q' => 15, // Payment Method
            'R' => 12, // COD Amount
            'S' => 15, // Package Type
            'T' => 8,  // Quantity
            'U' => 12, // Declared Value
            'V' => 25, // Description
            'W' => 20, // Driver
            'X' => 18, // Expected Delivery Date
            'Y' => 18, // Actual Delivery Date
            'Z' => 18, // Pickup Date
            'AA' => 25, // Notes
            'AB' => 18, // Created At
        ];
    }
}
