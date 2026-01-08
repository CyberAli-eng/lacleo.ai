<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\CreditTransaction;
use App\Models\Subscription;
use App\Models\Workspace;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Stripe\StripeClient;
use Symfony\Component\HttpFoundation\Response;

class BillingController extends Controller
{
    public function usage(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        try {
            $workspace = Workspace::firstOrCreate(
                ['owner_user_id' => $user->id],
                ['id' => (string) strtolower(Str::ulid()), 'credit_balance' => 0, 'credit_reserved' => 0]
            );

            $subscription = Subscription::where('workspace_id', $workspace->id)->orderByDesc('created_at')->first();

            $periodStart = $subscription && $subscription->current_period_end
                ? Carbon::parse($subscription->current_period_end)->subMonth()->startOfDay()
                : Carbon::now()->startOfMonth();

            $periodEnd = $subscription && $subscription->current_period_end
                ? Carbon::parse($subscription->current_period_end)
                : Carbon::now()->endOfMonth();

            $transactions = CreditTransaction::where('workspace_id', $workspace->id)
                ->whereBetween('created_at', [$periodStart, $periodEnd])
                ->get();

            $used = $transactions->where('amount', '<', 0)->sum(fn($t) => abs($t->amount));

            $revealEmail = $transactions
                ->filter(fn($t) => $t->type === 'spend' && is_array($t->meta) && (($t->meta['category'] ?? null) === 'reveal_email'))
                ->sum(fn($t) => abs($t->amount));
            $revealPhone = $transactions
                ->filter(fn($t) => $t->type === 'spend' && is_array($t->meta) && (($t->meta['category'] ?? null) === 'reveal_phone'))
                ->sum(fn($t) => abs($t->amount));
        } catch (\Throwable $e) {
            return response()->json([
                'balance' => 0,
                'plan_total' => 0,
                'used' => 0,
                'period_start' => Carbon::now()->startOfMonth()->toIso8601String(),
                'period_end' => Carbon::now()->endOfMonth()->toIso8601String(),
                'breakdown' => [
                    'reveal_email' => 0,
                    'reveal_phone' => 0,
                    'export_email' => 0,
                    'export_phone' => 0,
                    'adjustments' => 0,
                ],
                'free_grants_total' => 0,
                'stripe_enabled' => false,
            ]);
        }

        // Exports are recorded with meta email_count and phone_count; compute per-category credits
        $exportEmailCredits = $transactions
            ->filter(fn($t) => $t->type === 'spend' && is_array($t->meta) && (($t->meta['category'] ?? null) === 'export'))
            ->sum(fn($t) => (int) (($t->meta['email_count'] ?? 0) * 1));
        $exportPhoneCredits = $transactions
            ->filter(fn($t) => $t->type === 'spend' && is_array($t->meta) && (($t->meta['category'] ?? null) === 'export'))
            ->sum(fn($t) => (int) (($t->meta['phone_count'] ?? 0) * 4));

        $adjustmentsDebited = $transactions
            ->filter(fn($t) => $t->type === 'adjustment' && $t->amount < 0)
            ->sum(fn($t) => abs($t->amount));

        $breakdown = [
            'reveal_email' => (int) $revealEmail,
            'reveal_phone' => (int) $revealPhone,
            'export_email' => (int) $exportEmailCredits,
            'export_phone' => (int) $exportPhoneCredits,
            'adjustments' => (int) $adjustmentsDebited,
        ];

        $stripeSecret = Config::get('services.stripe.secret');
        $pack500 = (int) env('CREDIT_PACK_500_AMOUNT', 0);
        $pack2000 = (int) env('CREDIT_PACK_2000_AMOUNT', 0);
        $pack10000 = (int) env('CREDIT_PACK_10000_AMOUNT', 0);
        $stripeEnabled = (bool) ($stripeSecret && ($pack500 > 0 || $pack2000 > 0 || $pack10000 > 0));

        return response()->json([
            'balance' => (int) $workspace->credit_balance,
            'plan_total' => 0,
            'used' => (int) $used,
            'period_start' => $periodStart->toIso8601String(),
            'period_end' => $periodEnd->toIso8601String(),
            'breakdown' => $breakdown,
            'free_grants_total' => (int) $transactions->filter(fn($t) => $t->type === 'adjustment' && $t->amount > 0)->sum(fn($t) => $t->amount),
            'stripe_enabled' => $stripeEnabled,
        ]);
    }

    public function grantCredits(Request $request)
    {
        $admin = $request->user();

        $adminEmails = array_map('strtolower', array_filter(array_map('trim', explode(',', (string) env('ADMIN_EMAILS', '')))));
        $isAdmin = in_array(strtolower($admin->email ?? ''), $adminEmails, true);
        if (!$isAdmin) {
            return response()->json(['error' => 'ADMIN_REQUIRED'], 403);
        }

        $validated = $request->validate([
            'user_id' => 'required|string|exists:users,id',
            'credits' => 'required|integer|min:1|max:100000',
            'reason' => 'nullable|string',
        ]);

        $targetUserId = $validated['user_id'];

        if ($admin->id === $targetUserId) {
            abort(403);
        }

        $targetWorkspace = Workspace::firstOrCreate(
            ['owner_user_id' => $targetUserId],
            ['id' => (string) strtolower(Str::ulid()), 'credit_balance' => 0, 'credit_reserved' => 0]
        );

        DB::transaction(function () use ($targetWorkspace, $validated, $admin, $targetUserId) {
            $ws = Workspace::where('id', $targetWorkspace->id)->lockForUpdate()->first();
            $ws->increment('credit_balance', (int) $validated['credits']);

            \App\Models\CreditGrant::create([
                'id' => (string) strtolower(Str::ulid()),
                'user_id' => $targetUserId,
                'granted_by' => $admin->id,
                'credits' => (int) $validated['credits'],
                'reason' => $validated['reason'] ?? null,
            ]);

            CreditTransaction::create([
                'id' => (string) strtolower(Str::ulid()),
                'workspace_id' => $ws->id,
                'amount' => (int) $validated['credits'],
                'type' => 'adjustment',
                'meta' => ['reason' => ($validated['reason'] ?? 'free_grant'), 'granted_by' => $admin->id],
            ]);
        });

        $fresh = Workspace::find($targetWorkspace->id);

        $resp = response()->json([
            'success' => true,
            'new_balance' => (int) ($fresh->credit_balance ?? 0),
        ]);
        app(\App\Services\AuditLogger::class)->log('admin', (int) $admin->id, 'grant_credits', [
            'target_id' => $targetUserId,
            'workspace_id' => $fresh->id,
            'amount' => (int) $validated['credits'],
            'reason' => $validated['reason'] ?? null,
        ]);

        return $resp;
    }

    public function purchase(Request $request)
    {
        $user = $request->user();
        $workspace = Workspace::firstOrCreate(
            ['owner_user_id' => $user->id],
            ['id' => (string) strtolower(Str::ulid()), 'credit_balance' => 0, 'credit_reserved' => 0]
        );

        $secret = Config::get('services.stripe.secret');
        $pack = (int) $request->input('pack');

        if (!$secret || !in_array($pack, [500, 2000, 10000], true)) {
            return response()->json(['error' => 'Invalid pack'], 400);
        }

        $stripe = new StripeClient($secret);

        if (!$workspace->stripe_customer_id) {
            $customer = $stripe->customers->create(['email' => $user->email, 'name' => $user->name]);
            $workspace->update(['stripe_customer_id' => $customer->id]);
        }

        $amountKey = match ($pack) {
            500 => 'CREDIT_PACK_500_AMOUNT',
            2000 => 'CREDIT_PACK_2000_AMOUNT',
            10000 => 'CREDIT_PACK_10000_AMOUNT',
        };
        $unitAmount = (int) env($amountKey, 0) * 100;
        if ($unitAmount <= 0) {
            return response()->json(['error' => 'Stripe pack amount not configured'], 500);
        }

        $session = $stripe->checkout->sessions->create([
            'mode' => 'payment',
            'line_items' => [
                [
                    'price_data' => [
                        'currency' => 'usd',
                        'product_data' => ['name' => "Credits Pack {$pack}"],
                        'unit_amount' => $unitAmount,
                    ],
                    'quantity' => 1,
                ]
            ],
            'success_url' => url('/billing/success') . '?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => url('/billing/cancel'),
            'customer' => $workspace->stripe_customer_id,
            'metadata' => [
                'workspace_id' => $workspace->id,
                'type' => 'purchase',
                'credits' => $pack,
                'user_id' => $user->id,
            ],
        ]);

        $resp = response()->json(['checkout_url' => $session->url]);
        app(\App\Services\AuditLogger::class)->log('user', (int) $user->id, 'purchase', [
            'workspace_id' => $workspace->id,
            'pack' => $pack,
        ]);

        return $resp;
    }

    public function subscribe(Request $request)
    {
        $user = $request->user();
        $workspace = Workspace::firstOrCreate(
            ['owner_user_id' => $user->id],
            ['id' => (string) strtolower(Str::ulid()), 'credit_balance' => 0, 'credit_reserved' => 0]
        );

        $secret = Config::get('services.stripe.secret');
        $planId = $request->input('plan_id');

        if (!$secret || !$planId) {
            return response()->json(['error' => 'Stripe not configured'], 500);
        }

        $stripe = new StripeClient($secret);

        if (!$workspace->stripe_customer_id) {
            $customer = $stripe->customers->create(['email' => $user->email, 'name' => $user->name]);
            $workspace->update(['stripe_customer_id' => $customer->id]);
        }

        $plan = \App\Models\Plan::find($planId);
        if (!$plan || !$plan->stripe_price_id) {
            return response()->json(['error' => 'Invalid plan'], 400);
        }

        $session = $stripe->checkout->sessions->create([
            'mode' => 'subscription',
            'line_items' => [
                [
                    'price' => $plan->stripe_price_id,
                    'quantity' => 1,
                ]
            ],
            'success_url' => url('/billing/success') . '?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => url('/billing/cancel'),
            'customer' => $workspace->stripe_customer_id,
            'metadata' => [
                'workspace_id' => $workspace->id,
                'type' => 'subscription',
                'user_id' => $user->id,
                'plan_id' => $plan->id,
            ],
        ]);

        return response()->json(['checkout_url' => $session->url]);
    }

    public function portal(Request $request)
    {
        $user = $request->user();
        $secret = Config::get('services.stripe.secret');

        if (!$secret) {
            return response()->json(['error' => 'Stripe not configured'], 500);
        }

        $stripe = new StripeClient($secret);

        $workspace = Workspace::firstOrCreate(
            ['owner_user_id' => $user->id],
            ['id' => (string) strtolower(Str::ulid()), 'credit_balance' => 0, 'credit_reserved' => 0]
        );

        if (!$workspace->stripe_customer_id) {
            $customer = $stripe->customers->create(['email' => $user->email, 'name' => $user->name]);
            $workspace->update(['stripe_customer_id' => $customer->id]);
        }

        $session = $stripe->billingPortal->sessions->create([
            'customer' => $workspace->stripe_customer_id,
            'return_url' => url('/'),
        ]);

        return response()->json(['url' => $session->url]);
    }

    public function webhookStripe(Request $request)
    {
        $secret = Config::get('services.stripe.secret');
        $webhookSecret = Config::get('services.stripe.webhook_secret');

        if (app()->environment('testing')) {
            $event = json_decode(json_encode($request->input()));
        } else {
            if (!$secret || !$webhookSecret) {
                return response('', Response::HTTP_BAD_REQUEST);
            }

            $payload = $request->getContent();
            $sigHeader = $request->header('Stripe-Signature');

            try {
                $event = \Stripe\Webhook::constructEvent($payload, $sigHeader, $webhookSecret);
            } catch (\Exception $e) {
                return response('', Response::HTTP_BAD_REQUEST);
            }
        }

        if ($event->type === 'checkout.session.completed') {
            $session = $event->data->object;
            $meta = property_exists($session, 'metadata') ? (is_array($session->metadata) ? $session->metadata : (array) $session->metadata) : [];
            $workspaceId = $meta['workspace_id'] ?? null;
            if ($workspaceId) {
                DB::transaction(function () use ($workspaceId, $session, $meta) {
                    $workspace = Workspace::find($workspaceId);
                    if (!$workspace) {
                        return;
                    }
                    if ((property_exists($session, 'customer') ? $session->customer : null) && !$workspace->stripe_customer_id) {
                        $workspace->update(['stripe_customer_id' => $session->customer]);
                    }
                    $mode = property_exists($session, 'mode') ? $session->mode : null;
                    if ($mode === 'payment') {
                        $credits = (int) ($meta['credits'] ?? 0);
                        if ($credits > 0) {
                            if (CreditTransaction::where('workspace_id', $workspace->id)->where('reference_id', $session->id)->exists()) {
                                return;
                            }
                            $workspace->increment('credit_balance', $credits);
                            CreditTransaction::create([
                                'workspace_id' => $workspace->id,
                                'amount' => $credits,
                                'type' => 'purchase',
                                'reference_id' => $session->id,
                                'meta' => ['category' => 'purchase'],
                            ]);
                        }
                    }
                    if ($mode === 'subscription') {
                        $subId = property_exists($session, 'subscription') ? $session->subscription : null;
                        Subscription::updateOrCreate(
                            ['workspace_id' => $workspace->id, 'provider' => 'stripe'],
                            [
                                'stripe_subscription_id' => $subId,
                                'status' => 'active',
                                'cancel_at_period_end' => false,
                            ]
                        );
                    }
                });
            }
        }

        if ($event->type === 'invoice.payment_succeeded') {
            $invoice = $event->data->object;
            $subscriptionId = $invoice->subscription;
            $priceId = null;
            $invoiceArr = json_decode(json_encode($invoice), true);
            $priceId = $invoiceArr['lines']['data'][0]['price']['id'] ?? null;
            if ($subscriptionId && $priceId) {
                DB::transaction(function () use ($subscriptionId, $priceId, $invoice) {
                    $subscription = Subscription::where('stripe_subscription_id', $subscriptionId)->first();
                    if (!$subscription) {
                        return;
                    }
                    $plan = \App\Models\Plan::where('stripe_price_id', $priceId)->first();
                    if (!$plan) {
                        return;
                    }
                    $workspace = Workspace::find($subscription->workspace_id);
                    if (!$workspace) {
                        return;
                    }
                    $credits = (int) $plan->monthly_credits;
                    if ($credits > 0) {
                        if (CreditTransaction::where('workspace_id', $workspace->id)->where('reference_id', $invoice->id)->exists()) {
                            return;
                        }
                        $workspace->increment('credit_balance', $credits);
                        CreditTransaction::create([
                            'workspace_id' => $workspace->id,
                            'amount' => $credits,
                            'type' => 'purchase',
                            'reference_id' => $invoice->id,
                            'meta' => ['category' => 'subscription'],
                        ]);
                    }
                    $subscription->update([
                        'status' => 'active',
                        'current_period_end' => now()->addMonth(),
                        'cancel_at_period_end' => false,
                    ]);
                });
            }
        }

        if ($event->type === 'customer.subscription.updated') {
            $sub = $event->data->object;
            $status = $sub->status;
            Subscription::where('stripe_subscription_id', $sub->id)->update([
                'status' => $status,
                'cancel_at_period_end' => (bool) ($sub->cancel_at_period_end ?? false),
                'current_period_end' => isset($sub->current_period_end) ? Carbon::createFromTimestamp($sub->current_period_end) : null,
            ]);
        }

        return response('', Response::HTTP_OK);
    }
}
