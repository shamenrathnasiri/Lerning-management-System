<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PaymentController extends Controller
{
    public function index(Request $request)
    {
        $query = Payment::with('user:id,name,email', 'course:id,title,slug', 'coupon:id,code');

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        return response()->json(
            $query->latest()->paginate($request->integer('per_page', 15))
        );
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'course_id' => ['required', 'exists:courses,id'],
            'amount' => ['required', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'max:3'],
            'payment_method' => ['nullable', 'string', 'max:50'],
            'payment_gateway' => ['nullable', 'string', 'max:50'],
            'coupon_code' => ['nullable', 'string', 'exists:coupons,code'],
        ]);

        $discountAmount = 0;
        $couponId = null;

        if (! empty($validated['coupon_code'])) {
            $coupon = Coupon::where('code', $validated['coupon_code'])
                ->where('is_active', true)
                ->where(function ($q) {
                    $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
                })
                ->first();

            if ($coupon) {
                $discountAmount = $coupon->type === 'percentage'
                    ? ($validated['amount'] * $coupon->value / 100)
                    : $coupon->value;

                $couponId = $coupon->id;
                $coupon->increment('used_count');
            }
        }

        $payment = Payment::create([
            'user_id' => $request->user()->id,
            'course_id' => $validated['course_id'],
            'coupon_id' => $couponId,
            'transaction_id' => 'TXN-' . strtoupper(Str::random(12)),
            'amount' => $validated['amount'],
            'discount_amount' => $discountAmount,
            'currency' => $validated['currency'] ?? 'USD',
            'payment_method' => $validated['payment_method'] ?? null,
            'payment_gateway' => $validated['payment_gateway'] ?? null,
            'status' => 'pending',
        ]);

        return response()->json($payment, 201);
    }

    public function show(Payment $payment)
    {
        return response()->json(
            $payment->load('user:id,name,email', 'course:id,title', 'coupon:id,code,type,value')
        );
    }

    public function updateStatus(Request $request, Payment $payment)
    {
        $validated = $request->validate([
            'status' => ['required', 'in:pending,completed,failed,refunded'],
            'gateway_response' => ['nullable', 'array'],
        ]);

        $updates = ['status' => $validated['status']];

        if ($validated['status'] === 'completed') {
            $updates['paid_at'] = now();
        }

        if ($validated['status'] === 'refunded') {
            $updates['refunded_at'] = now();
        }

        if (isset($validated['gateway_response'])) {
            $updates['gateway_response'] = $validated['gateway_response'];
        }

        $payment->update($updates);

        return response()->json($payment);
    }

    public function myPayments(Request $request)
    {
        return response()->json(
            Payment::with('course:id,title,slug')
                ->where('user_id', $request->user()->id)
                ->latest()
                ->paginate($request->integer('per_page', 15))
        );
    }
}
