<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\HeldOrder;
use App\Models\Sale;
use App\Models\SalePayment;
use App\Models\User;
use App\Services\CheckoutService;
use App\Services\CodeImageService;
use App\Support\Tenancy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PosSaleController extends Controller
{
    public function __construct(
        protected CheckoutService $checkout,
        protected CodeImageService $codes,
    ) {}

    /** Take payment and record the sale. */
    public function checkout(Request $request): JsonResponse
    {
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', Rule::exists('products', 'id')],
            'items.*.qty' => ['required', 'numeric', 'gt:0'],
            'items.*.discount_amount' => ['nullable', 'numeric', 'min:0'],
            'items.*.note' => ['nullable', 'string', 'max:191'],

            'discount_type' => ['nullable', Rule::in(['none', 'amount', 'percent'])],
            'discount_value' => ['nullable', 'numeric', 'min:0'],

            'payments' => ['required', 'array', 'min:1'],
            'payments.*.method' => ['required', Rule::in(array_keys(SalePayment::methods()))],
            'payments.*.amount' => ['required', 'numeric', 'gt:0'],
            'payments.*.reference' => ['nullable', 'string', 'max:100'],

            'customer_id' => ['nullable', 'integer', Rule::exists('customers', 'id')],
            'note' => ['nullable', 'string', 'max:500'],
            'held_order_id' => ['nullable', 'integer'],
        ], [], [
            'items' => 'keranjang',
            'payments' => 'pembayaran',
        ]);

        $cashier = auth('pos')->user();
        $shift = $cashier->openShift();

        if (! $shift) {
            return response()->json(['message' => 'Shift belum dibuka.'], 409);
        }

        $sale = $this->checkout->checkout(
            app(Tenancy::class)->get(),
            $cashier,
            $shift,
            $data,
        );

        // A parked cart that has now been paid for is no longer needed.
        if (! empty($data['held_order_id'])) {
            HeldOrder::where('id', $data['held_order_id'])
                ->where('user_id', $cashier->id)
                ->delete();
        }

        ActivityLog::record(
            'sale.create',
            "Transaksi {$sale->invoice_number} sebesar ".money($sale->total),
            $sale,
            ['total' => (float) $sale->total],
            $cashier,
            'pos',
        );

        return response()->json([
            'sale_id' => $sale->id,
            'invoice_number' => $sale->invoice_number,
            'total' => (float) $sale->total,
            'paid' => (float) $sale->paid_amount,
            'change' => (float) $sale->change_amount,
            'receipt_url' => route('pos.receipt', $sale),
        ], 201);
    }

    /** The premium thermal receipt, opened in a print window. */
    public function receipt(Request $request, Sale $sale): View
    {
        $cashier = auth('pos')->user();

        if ($sale->user_id !== $cashier->id && ! $cashier->hasPermission('sale.view')) {
            abort(403, 'Anda hanya dapat membuka struk transaksi sendiri.');
        }

        $sale->load(['items', 'payments', 'user', 'customer']);

        return view('print.receipt', [
            'sale' => $sale,
            'tenant' => app(Tenancy::class)->get(),
            'qrDataUri' => $this->receiptQr($sale),
            'reprint' => $request->boolean('reprint'),
            'autoPrint' => $request->boolean('auto', true),
        ]);
    }

    /** The operator's own transactions for the current shift. */
    public function history(Request $request): View
    {
        $cashier = auth('pos')->user();

        $sales = Sale::with(['payments', 'customer'])
            ->where('user_id', $cashier->id)
            ->whereDate('created_at', today())
            ->latest('created_at')
            ->paginate(20);

        return view('pos.history', [
            'sales' => $sales,
            'cashier' => $cashier,
            'shift' => $cashier->openShift(),
        ]);
    }

    /**
     * Cancel a transaction.
     *
     * A Kasir cannot approve their own void, so the form collects a
     * supervisor's PIN and that operator is recorded as the approver.
     */
    public function void(Request $request, Sale $sale): RedirectResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:191'],
            'approver_id' => ['required', 'integer'],
            'pin' => ['required', 'string', 'min:4', 'max:8'],
        ], [], [
            'reason' => 'alasan pembatalan',
            'approver_id' => 'penyetuju',
            'pin' => 'PIN',
        ]);

        $approver = User::find($data['approver_id']);

        if (! $approver || ! $approver->pos_pin || ! Hash::check($data['pin'], $approver->pos_pin)) {
            throw ValidationException::withMessages(['pin' => 'PIN penyetuju tidak cocok.']);
        }

        if (! $approver->hasPermission('sale.void')) {
            throw ValidationException::withMessages([
                'approver_id' => 'Hanya Owner atau Supervisor yang dapat menyetujui pembatalan.',
            ]);
        }

        $this->checkout->void($sale, $approver, $data['reason']);

        return back()->with('status', "Transaksi {$sale->invoice_number} dibatalkan oleh {$approver->name}.");
    }

    /** Park the current cart so the next customer can be served. */
    public function hold(Request $request): JsonResponse
    {
        $data = $request->validate([
            'label' => ['nullable', 'string', 'max:100'],
            'payload' => ['required', 'array'],
            'payload.items' => ['required', 'array', 'min:1'],
            'item_count' => ['nullable', 'integer', 'min:0'],
            'total' => ['nullable', 'numeric', 'min:0'],
        ]);

        $cashier = auth('pos')->user();

        $held = HeldOrder::create([
            'user_id' => $cashier->id,
            'reference' => 'HOLD-'.strtoupper(Str::random(6)),
            'label' => $data['label'] ?? null,
            'payload' => $data['payload'],
            'item_count' => $data['item_count'] ?? count($data['payload']['items']),
            'total' => $data['total'] ?? 0,
        ]);

        return response()->json(['held' => $held], 201);
    }

    public function heldList(): JsonResponse
    {
        return response()->json([
            'held' => HeldOrder::where('user_id', auth('pos')->id())
                ->latest()
                ->get(),
        ]);
    }

    public function releaseHold(HeldOrder $heldOrder): JsonResponse
    {
        if ($heldOrder->user_id !== auth('pos')->id()) {
            abort(403);
        }

        $heldOrder->delete();

        return response()->json(['deleted' => true]);
    }

    protected function receiptQr(Sale $sale): string
    {
        $payload = implode('|', [
            $sale->invoice_number,
            $sale->created_at->format('Y-m-d H:i'),
            number_format((float) $sale->total, 2, '.', ''),
        ]);

        return $this->codes->qrPngDataUri($payload, 180);
    }
}
