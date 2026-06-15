<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SalesInvoice;
use App\Models\SalesOrder;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class ExportController extends Controller
{
    public function invoicePdf($id)
    {
        $invoice = SalesInvoice::with(['customer', 'details.product', 'salesOrder'])->findOrFail($id);

        $pdf = Pdf::loadView('pdf.invoice', [
            'invoice' => $invoice,
        ]);

        return $pdf->download('invoice-' . $invoice->invoice_number . '.pdf');
    }

    public function salesOrderPdf($id)
    {
        $so = SalesOrder::with(['customer', 'details.product.brand', 'deliveryOrders'])->findOrFail($id);

        $pdf = Pdf::loadView('pdf.sales-order', [
            'so' => $so,
        ]);

        return $pdf->download('so-' . $so->so_number . '.pdf');
    }

    public function deliveryOrderPdf($id)
    {
        $do = \App\Models\DeliveryOrder::with(['customer', 'details.product', 'salesOrder'])->findOrFail($id);

        $pdf = Pdf::loadView('pdf.delivery-order', [
            'do' => $do,
        ]);

        return $pdf->download('sj-' . $do->do_number . '.pdf');
    }
}