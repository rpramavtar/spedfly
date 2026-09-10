<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Laravel\Passport\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'type',
        'status',
        'avatar_path',
        'company_name',
        'brand_name',
        'support_phone',
        'vat_number',
        'business_type',
        'pickup_name',
        'pickup_address',
        'pickup_city',
        'pickup_postal_code',
        'pickup_latitude',
        'pickup_longitude',
        'bank_name',
        'iban_code',
        'account_holder',
        'account_number',
        'ifsc_code',
        'branch',
        'shopify_shop_domain',
        'shopify_access_token',
        'shopify_access_token_expires_at',
        'shopify_refresh_token',
        'shopify_refresh_token_expires_at',
        'shopify_scope',
        'shopify_connected_at',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'shopify_connected_at' => 'datetime',
        'shopify_access_token_expires_at' => 'datetime',
        'shopify_refresh_token_expires_at' => 'datetime',
    ];

    public function isAdmin(): bool
    {
        return $this->type === 'admin';
    }

    public function isSeller(): bool
    {
        return $this->type === 'seller';
    }

    public function feeRules(): HasMany
    {
        return $this->hasMany(SellerFeeRule::class, 'seller_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'seller_id');
    }

    public function wallet(): HasOne
    {
        return $this->hasOne(Wallet::class, 'seller_id');
    }

    public function withdrawalRequests(): HasMany
    {
        return $this->hasMany(WithdrawalRequest::class, 'seller_id');
    }

    public function getOrCreateWallet()
    {
        return $this->wallet()->firstOrCreate(
            ['seller_id' => $this->id],
            [
                'balance' => 0.00,
                'min_threshold' => 100.00,
            ]
        );
    }

    public function chargeWallet($amount, $orderId = null, $description = null)
    {
        $wallet = $this->getOrCreateWallet();
        
        $wallet->balance -= $amount;
        $wallet->save();

        $transaction = $wallet->transactions()->create([
            'amount' => -$amount,
            'type' => 'charge',
            'description' => $description,
            'reference_id' => $orderId,
            'balance_after' => $wallet->balance,
        ]);

        if ($wallet->balance < $wallet->min_threshold) {
            \App\Models\AppNotification::query()->create([
                'user_id' => $this->id,
                'title' => __('ui.low_wallet_balance_title') ?: 'Low Wallet Balance Alert',
                'message' => __('ui.low_wallet_balance_message', ['balance' => system_currency_format($wallet->balance)]) ?: "Your wallet balance is below the minimum threshold. Current balance: " . system_currency_format($wallet->balance),
                'type' => 'wallet_low_balance',
                'is_read' => false,
                'read_at' => null,
                'data' => [
                    'wallet_id' => $wallet->id,
                    'balance' => $wallet->balance,
                    'min_threshold' => $wallet->min_threshold,
                ],
            ]);

            try {
                $email = $this->email;
                $sellerName = $this->name;
                $balanceStr = system_currency_format($wallet->balance);
                $thresholdStr = system_currency_format($wallet->min_threshold);

                \Illuminate\Support\Facades\Mail::send([], [], function ($message) use ($email, $sellerName, $balanceStr, $thresholdStr) {
                    $message->to($email)
                        ->subject("Spedfly - Low Wallet Balance Alert")
                        ->html("
                            <div style='font-family: Arial, sans-serif; padding: 20px; color: #333;'>
                                <h2>Hello {$sellerName},</h2>
                                <p>This is an automated alert to notify you that your wallet balance has dropped below the minimum threshold limit set for your account.</p>
                                <table style='width: 100%; border-collapse: collapse; margin: 20px 0;'>
                                    <tr style='background-color: #f8f9fa;'>
                                        <th style='padding: 10px; border: 1px solid #dee2e6; text-align: left;'>Current Balance</th>
                                        <td style='padding: 10px; border: 1px solid #dee2e6; font-weight: bold; color: #dc3545;'>{$balanceStr}</td>
                                    </tr>
                                    <tr>
                                        <th style='padding: 10px; border: 1px solid #dee2e6; text-align: left;'>Minimum Threshold</th>
                                        <td style='padding: 10px; border: 1px solid #dee2e6;'>{$thresholdStr}</td>
                                    </tr>
                                </table>
                                <p>Please top up your wallet balance in your Spedfly dashboard to ensure order commissions can be paid seamlessly.</p>
                                <p>Best regards,<br><strong>Spedfly Team</strong></p>
                            </div>
                        ");
                });
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error("Failed to send low balance email to seller {$this->email}: " . $e->getMessage());
            }
        }

        return $transaction;
    }

    public function topupWallet($amount, $description = null, $orderId = null)
    {
        $wallet = $this->getOrCreateWallet();
        
        $wallet->balance += $amount;
        $wallet->save();

        return $wallet->transactions()->create([
            'amount' => $amount,
            'type' => 'topup',
            'description' => $description,
            'reference_id' => $orderId,
            'balance_after' => $wallet->balance,
        ]);
    }
}
