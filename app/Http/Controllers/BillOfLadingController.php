<?php

namespace App\Http\Controllers;

use App\Models\Shipment;
use Illuminate\Http\Request;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\Style\Font;

class BillOfLadingController extends Controller
{
    public function generateBillOfLading($shipmentId)
    {
        // Get shipment data
        $shipment = Shipment::with(['seller', 'driver'])->findOrFail($shipmentId);

        // Create new Word document
        $phpWord = new PhpWord();

        // Set document properties
        $properties = $phpWord->getDocInfo();
        $properties->setCreator('DP Shipping Company');
        $properties->setTitle('Bill of Lading - ' . $shipment->tracking_number);

        // Add section with smaller margins for more professional look
        $section = $phpWord->addSection([
            'marginLeft' => 200,
            'marginRight' => 200,
            'marginTop' => 200,
            'marginBottom' => 200,
        ]);

        // Generate the bill content
        self::addBillContent($section, $shipment);

        // Generate filename
        $filename = 'Bill_of_Lading_' . $shipment->tracking_number . '_' . now()->format('Y-m-d') . '.docx';

        // Save and download
        $objWriter = IOFactory::createWriter($phpWord, 'Word2007');

        $temp_file = tempnam(sys_get_temp_dir(), 'bill_of_lading');
        $objWriter->save($temp_file);

        return response()->download($temp_file, $filename)->deleteFileAfterSend(true);
    }

    // Static method for generating multiple bills in ONE Word document
    public static function generateMultipleBillsContent($shipments)
    {
        // Create new Word document
        $phpWord = new PhpWord();

        // Set document properties
        $properties = $phpWord->getDocInfo();
        $properties->setCreator('DP Shipping Company');
        $properties->setTitle('Bills of Lading - ' . count($shipments) . ' Shipments');

        // Create ONE section for all bills
        $section = $phpWord->addSection([
            'marginLeft' => 400,
            'marginRight' => 400,
            'marginTop' => 400,
            'marginBottom' => 400,
        ]);

        $isFirstBill = true;

        foreach ($shipments as $shipment) {
            // Add space between bills (except for the first one)
            if (!$isFirstBill) {
                $section->addTextBreak(3); // Add space between bills
                $section->addText('─────────────────────────────────────────────────────────────────',
                    ['name' => 'Arial', 'size' => 5, 'color' => 'CCCCCC'],
                    ['alignment' => 'center']); // Add separator line
                $section->addTextBreak(1);
            } else {
                $isFirstBill = false;
            }

            // Generate the bill content for this shipment
            self::addBillContent($section, $shipment);
        }

        // Save to temp file and return content
        $temp_file = tempnam(sys_get_temp_dir(), 'multiple_bills');
        $objWriter = IOFactory::createWriter($phpWord, 'Word2007');
        $objWriter->save($temp_file);

        $content = file_get_contents($temp_file);
        unlink($temp_file);

        return $content;
    }

    // Static method for generating bill content that can be called from Filament
    public static function generateBillContent($shipment)
    {
        // Create new Word document
        $phpWord = new PhpWord();

        // Set document properties
        $properties = $phpWord->getDocInfo();
        $properties->setCreator('DP Shipping Company');
        $properties->setTitle('Bill of Lading - ' . $shipment->tracking_number);

        // Add section
        $section = $phpWord->addSection([
            'marginLeft' => 400,
            'marginRight' => 400,
            'marginTop' => 400,
            'marginBottom' => 400,
        ]);

        // Generate the bill content
        self::addBillContent($section, $shipment);

        // Save to temp file and return content
        $temp_file = tempnam(sys_get_temp_dir(), 'bill_of_lading');
        $objWriter = IOFactory::createWriter($phpWord, 'Word2007');
        $objWriter->save($temp_file);

        $content = file_get_contents($temp_file);
        unlink($temp_file);

        return $content;
    }

    // Extract the bill content generation to a reusable method
    private static function addBillContent($section, $shipment)
    {
        // Define styles similar to FedEx form
        $headerTitleStyle = ['name' => 'Arial', 'size' => 14, 'bold' => true];
        $companyStyle = ['name' => 'Arial', 'size' => 12, 'bold' => true];
        $labelStyle = ['name' => 'Arial', 'size' => 8, 'bold' => true];
        $dataStyle = ['name' => 'Arial', 'size' => 10];
        $smallTextStyle = ['name' => 'Arial', 'size' => 7];

        // Header Section - Company name and title
        $headerTable = $section->addTable(['borderSize' => 0]);
        $headerTable->addRow();
        $headerTable->addCell(6000)->addText('DP Shipping Company', $companyStyle);
        $headerTable->addCell(2000)->addText('Date: ' . now()->format('m/d/Y'), $dataStyle, ['alignment' => 'right']);

        $section->addTextBreak(1);

        // Top section with shipper and consignee
        $topTable = $section->addTable([
            'borderSize' => 12,
            'borderColor' => '000000',
            'cellMargin' => 80,
            'unit' => 'pct',
            'width' => 5000
        ]);

        // Shipper and Consignee row
        $topTable->addRow();
        $shipperCell = $topTable->addCell(4000, [
            'borderTopSize' => 12,
            'borderLeftSize' => 12,
            'borderRightSize' => 6,
            'borderBottomSize' => 12,
            'borderTopColor' => '000000',
            'borderLeftColor' => '000000',
            'borderRightColor' => '000000',
            'borderBottomColor' => '000000'
        ]);
        $consigneeCell = $topTable->addCell(4000, [
            'borderTopSize' => 12,
            'borderLeftSize' => 6,
            'borderRightSize' => 12,
            'borderBottomSize' => 12,
            'borderTopColor' => '000000',
            'borderLeftColor' => '000000',
            'borderRightColor' => '000000',
            'borderBottomColor' => '000000'
        ]);

        // Shipper section
        $shipperCell->addText('SHIPPER (from)', $labelStyle);
        $shipperCell->addTextBreak(0.3);
        $shipperCell->addText($shipment->seller->company_name ?? 'N/A', $dataStyle);
        $shipperCell->addText(($shipment->sender_name ?? 'N/A'), $dataStyle);
        $shipperCell->addText(($shipment->sender_address ?? 'N/A'), $dataStyle);
        $shipperCell->addText(($shipment->sender_city ?? 'N/A'), $dataStyle);
        $shipperCell->addText('Phone: ' . ($shipment->sender_phone ?? 'N/A'), $dataStyle);

        // Consignee section
        $consigneeCell->addText('RECEIVER (to)', $labelStyle);
        $consigneeCell->addTextBreak(0.3);
        $consigneeCell->addText(($shipment->receiver_name ?? 'N/A'), $dataStyle);
        $consigneeCell->addText(($shipment->receiver_address ?? 'N/A'), $dataStyle);
        $consigneeCell->addText(($shipment->receiver_city ?? 'N/A'), $dataStyle);
        $consigneeCell->addText('Phone: ' . ($shipment->receiver_phone ?? 'N/A'), $dataStyle);

        $section->addTextBreak(0.5);

        // Billing and service information
        $billingTable = $section->addTable([
            'borderSize' => 12,
            'borderColor' => '000000',
            'cellMargin' => 80,
            'unit' => 'pct',
            'width' => 5000
        ]);
        $billingTable->addRow();

        $billingCell = $billingTable->addCell(2667, [
            'borderTopSize' => 12, 'borderLeftSize' => 12, 'borderRightSize' => 6, 'borderBottomSize' => 12,
            'borderTopColor' => '000000', 'borderLeftColor' => '000000', 'borderRightColor' => '000000', 'borderBottomColor' => '000000'
        ]);
        $billingCell->addText('BILL FREIGHT CHARGES TO', $labelStyle);
        $billingCell->addText('☐ Shipper  ☐ Consignee  ☐ Third Party', $dataStyle);
        $billingCell->addTextBreak(0.3);
        $billingCell->addText('Account: ' . ($shipment->seller->company_name ?? 'N/A'), $dataStyle);

        $serviceCell = $billingTable->addCell(2667, [
            'borderTopSize' => 12, 'borderLeftSize' => 6, 'borderRightSize' => 6, 'borderBottomSize' => 12,
            'borderTopColor' => '000000', 'borderLeftColor' => '000000', 'borderRightColor' => '000000', 'borderBottomColor' => '000000'
        ]);
        $serviceCell->addText('SERVICE TYPE', $labelStyle);
        $serviceType = $shipment->shipment_type ?? 'standard';
        $serviceCell->addText(($serviceType === 'standard' ? '☑' : '☐') . ' Standard Delivery', $dataStyle);
        $serviceCell->addText(($serviceType === 'express' ? '☑' : '☐') . ' Express Delivery', $dataStyle);
        $serviceCell->addText(($serviceType === 'same_day' ? '☑' : '☐') . ' Same-day Delivery', $dataStyle);

        $trackingCell = $billingTable->addCell(2666, [
            'borderTopSize' => 12, 'borderLeftSize' => 6, 'borderRightSize' => 12, 'borderBottomSize' => 12,
            'borderTopColor' => '000000', 'borderLeftColor' => '000000', 'borderRightColor' => '000000', 'borderBottomColor' => '000000'
        ]);
        $trackingCell->addText('TRACKING NUMBER', $labelStyle);
        $trackingCell->addText($shipment->tracking_number, ['name' => 'Arial', 'size' => 12, 'bold' => true, 'color' => 'FF0000']);

        $section->addTextBreak(0.5);

        // Main shipment details table
        $detailsTable = $section->addTable([
            'borderSize' => 12,
            'borderColor' => '000000',
            'cellMargin' => 80,
            'unit' => 'pct',
            'width' => 5000
        ]);

        // Header row
        $detailsTable->addRow();
        $detailsTable->addCell(1000, self::getCellStyle())->addText('HM (X)', $labelStyle, ['alignment' => 'center']);
        $detailsTable->addCell(1000, self::getCellStyle())->addText('PIECES', $labelStyle, ['alignment' => 'center']);
        $detailsTable->addCell(1000, self::getCellStyle())->addText('TYPE', $labelStyle, ['alignment' => 'center']);
        $detailsTable->addCell(3000, self::getCellStyle())->addText('KIND OF PACKAGE, DESCRIPTION OF ARTICLES, SPECIAL MARKS', $labelStyle, ['alignment' => 'center']);
        $detailsTable->addCell(1000, self::getCellStyle())->addText('WEIGHT', $labelStyle, ['alignment' => 'center']);

        // Data row
        $detailsTable->addRow();
        $detailsTable->addCell(1000, self::getCellStyle())->addText('', $dataStyle);
        $detailsTable->addCell(1000, self::getCellStyle())->addText($shipment->quantity ?? '1', $dataStyle, ['alignment' => 'center']);
        $detailsTable->addCell(1000, self::getCellStyle())->addText($shipment->package_type ?? 'PKG', $dataStyle, ['alignment' => 'center']);
        $detailsTable->addCell(3000, self::getCellStyle())->addText($shipment->description ?? 'General Goods', $dataStyle);
        $detailsTable->addCell(1000, self::getCellStyle())->addText(($shipment->weight ?? '0') . ' kg', $dataStyle, ['alignment' => 'center']);

        // Add empty rows for additional items
        for ($i = 0; $i < 3; $i++) {
            $detailsTable->addRow();
            $detailsTable->addCell(1000, self::getCellStyle())->addText('', $dataStyle);
            $detailsTable->addCell(1000, self::getCellStyle())->addText('', $dataStyle);
            $detailsTable->addCell(1000, self::getCellStyle())->addText('', $dataStyle);
            $detailsTable->addCell(3000, self::getCellStyle())->addText('', $dataStyle);
            $detailsTable->addCell(1000, self::getCellStyle())->addText('', $dataStyle);
        }

        $section->addTextBreak(0.5);

        // COD and charges section
        $chargesTable = $section->addTable([
            'borderSize' => 12,
            'borderColor' => '000000',
            'cellMargin' => 80,
            'unit' => 'pct',
            'width' => 5000
        ]);
        $chargesTable->addRow();

        $codCell = $chargesTable->addCell(2500, self::getCellStyle());
        $codCell->addText('SHIPMENT AMOUNT', $labelStyle, ['alignment' => 'center']);
        $codCell->addText('EGP ' . number_format($shipment->cod_amount ?? 0, 2), $dataStyle, ['alignment' => 'center']);

        $chargesCell = $chargesTable->addCell(2500, self::getCellStyle());
        $chargesCell->addText('SHIPPING COST', $labelStyle, ['alignment' => 'center']);
        $chargesCell->addText(' EGP ' . number_format($shipment->shipping_cost ?? 0, 2), $dataStyle, ['alignment' => 'center']);

        $totalCell = $chargesTable->addCell(3000, self::getCellStyle());
        $totalCell->addText('TOTAL', $labelStyle, ['alignment' => 'center']);
        $total = ($shipment->shipping_cost ?? 0) + ($shipment->cod_amount ?? 0);
        $totalCell->addText('EGP ' . number_format($total, 2), ['name' => 'Arial', 'size' => 12, 'bold' => true], ['alignment' => 'center']);

        $section->addTextBreak(0.5);

        // Delivery information
        $deliveryTable = $section->addTable([
            'borderSize' => 12,
            'borderColor' => '000000',
            'cellMargin' => 80,
            'unit' => 'pct',
            'width' => 5000
        ]);
        $deliveryTable->addRow();

        $pickupCell = $deliveryTable->addCell(2667, self::getCellStyle());
        $pickupCell->addText('PICKUP INFORMATION', $labelStyle);
        $pickupCell->addText('Pickup Date: ' . ($shipment->pickup_date ? $shipment->pickup_date->format('m/d/Y') : 'TBD'), $dataStyle);
        $pickupCell->addText('Driver: ' . ($shipment->driver->name ?? 'Not Assigned'), $dataStyle);

        $deliveryCell = $deliveryTable->addCell(2667, self::getCellStyle());
        $deliveryCell->addText('DELIVERY INFORMATION', $labelStyle);
        $deliveryCell->addText('Expected: ' . ($shipment->expected_delivery_date ? $shipment->expected_delivery_date->format('m/d/Y') : 'TBD'), $dataStyle);
        $deliveryCell->addText('Status: ' . ucfirst(str_replace('_', ' ', $shipment->status)), $dataStyle);

        $specialCell = $deliveryTable->addCell(2666, self::getCellStyle());
        $specialCell->addText('Notes :', $labelStyle);
        $specialCell->addText($shipment->notes ?? '', $smallTextStyle);

        $section->addTextBreak(0.5);


    }

    // Generate multiple Bills of Lading for selected shipments (Old ZIP method - kept for compatibility)
    public function generateMultipleBills(Request $request)
    {
        $shipmentIds = $request->input('shipment_ids', []);

        if (empty($shipmentIds)) {
            return back()->with('error', 'Please select at least one shipment.');
        }

        if (count($shipmentIds) === 1) {
            return $this->generateBillOfLading($shipmentIds[0]);
        }

        // For multiple shipments, create a ZIP file
        $zip = new \ZipArchive();
        $zipFileName = 'Bills_of_Lading_' . now()->format('Y-m-d_H-i-s') . '.zip';
        $zipPath = storage_path('app/temp/' . $zipFileName);

        // Ensure temp directory exists
        if (!file_exists(storage_path('app/temp'))) {
            mkdir(storage_path('app/temp'), 0755, true);
        }

        if ($zip->open($zipPath, \ZipArchive::CREATE) !== TRUE) {
            return back()->with('error', 'Cannot create ZIP file.');
        }

        foreach ($shipmentIds as $shipmentId) {
            $shipment = Shipment::with(['seller', 'driver'])->find($shipmentId);
            if (!$shipment) continue;

            // Create individual bill for this shipment
            $tempFile = $this->createIndividualBill($shipment);
            $filename = 'Bill_of_Lading_' . $shipment->tracking_number . '.docx';

            $zip->addFile($tempFile, $filename);
        }

        $zip->close();

        return response()->download($zipPath, $zipFileName)->deleteFileAfterSend(true);
    }

    private function createIndividualBill($shipment)
    {
        // This method would contain the same bill generation logic as above
        // but return a temp file path instead of a download response
        // Implementation similar to generateBillOfLading but saving to temp file

        $phpWord = new PhpWord();
        // ... (same document creation logic as above)

        $temp_file = tempnam(sys_get_temp_dir(), 'bill_' . $shipment->id);
        $objWriter = IOFactory::createWriter($phpWord, 'Word2007');
        $objWriter->save($temp_file);

        return $temp_file;
    }

    // Helper method for consistent cell styling
    private static function getCellStyle()
    {
        return [
            'borderTopSize' => 12,
            'borderLeftSize' => 12,
            'borderRightSize' => 12,
            'borderBottomSize' => 12,
            'borderTopColor' => '000000',
            'borderLeftColor' => '000000',
            'borderRightColor' => '000000',
            'borderBottomColor' => '000000'
        ];
    }

    // Helper method for compact cell styling
    private static function getCompactCellStyle()
    {
        return [
            'borderTopSize' => 8,
            'borderLeftSize' => 8,
            'borderRightSize' => 8,
            'borderBottomSize' => 8,
            'borderTopColor' => '000000',
            'borderLeftColor' => '000000',
            'borderRightColor' => '000000',
            'borderBottomColor' => '000000'
        ];
    }
}
