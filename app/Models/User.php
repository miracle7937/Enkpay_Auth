<?php

namespace App\Models;

use App\States\User\Activated;
use App\States\User\Active;
use Illuminate\Support\Str;
use App\Enums\AccountTypeEnum;
use App\States\User\UserStatus;
use App\Traits\MustVerifyPhone;
use Spatie\ModelStates\HasStates;
use Laravel\Sanctum\HasApiTokens;
use Bavix\Wallet\Traits\HasWallet;
use Illuminate\Support\Facades\DB;
use Bavix\Wallet\Traits\HasWallets;
use Bavix\Wallet\Interfaces\Wallet;
use Bavix\Wallet\Traits\CanConfirm;
use App\Support\Generators\OTPToken;
use App\States\User\UserSuspendedState;
use Bavix\Wallet\Interfaces\Confirmable;
use Illuminate\Notifications\Notifiable;
use Propaganistas\LaravelPhone\PhoneNumber;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Notifications\VerificationNotification;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Bavix\Wallet\Internal\Exceptions\ExceptionInterface;

class User extends Authenticatable implements Wallet, Confirmable
{
    use HasStates;
    use HasWallet;
    //use HasWallets;
    use CanConfirm;
    use Notifiable;
    use HasFactory;
    use SoftDeletes;
    use HasApiTokens;
    use MustVerifyPhone;

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
        'pin' => 'encrypted',
        'status' => UserStatus::class,
        'type' => AccountTypeEnum::class,
        'suspended_state' => UserSuspendedState::class,
        'date_of_birth' => 'datetime',
        'email_verified_at' => 'datetime',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (User $user) {
            $user->uuid = Str::orderedUuid();
        });
    }

    public function getPhoneNumberAttribute(): string
    {
        return (string) PhoneNumber::make($this->phone, $this->phone_country)
                ->formatE164();
    }

    public function getFullNameAttribute(): string
    {
        return "{$this->last_name} {$this->first_name} {$this->middle_name}";
    }

    public function phoneIsVerified(): bool
    {
        return !is_null($this->phone_verified_at);
    }

    public function accountIsVerified(): bool
    {
        return $this->status->equals(Active::class);
    }

    public function sendVerificationNotification(): void
    {
        $this->generateVerificationToken();

        $this->notify(new VerificationNotification());
    }

    public function generateVerificationToken(): void
    {
        DB::table('phone_verification_tokens')
            ->upsert([
                [
                    'phone' => $this->phone_number,
                    'token' => (string) OTPToken::generate(),
                    'created_at' => $this->freshTimestamp()
                ]
            ], ['phone'], ['token', 'created_at']);
    }

    public function getVerificationToken(): mixed
    {
        return DB::table('phone_verification_tokens')
            ->where('phone', $this->phone_number)
            ->value('token');
    }

    public function verifyAccount(): void
    {
        $this->markPhoneAsVerified();
        $this->status->transitionTo(Active::class);

        $this->deletePhoneVerificationToken();
    }

    public function isAdmin(): bool
    {
        return $this->type === "admin";
    }

    public function deletePhoneVerificationToken(): void
    {
        DB::table('phone_verification_tokens')
            ->where('phone', $this->phone_number)
            ->delete();
    }

    public function debit(int $amount, array $meta = [])
    {
        try {
            $this->withdraw($amount, $meta);
        } catch (ExceptionInterface $exception) {
            // handle exception
        }
    }

    public function credit(int $amount, array $meta = [])
    {
        try {
            $this->deposit($amount, $meta);
        } catch (ExceptionInterface $exception) {
            // handle exception
        }
    }

    public function accountBalance(): int
    {
        return $this->wallet->balanceInt;
    }

    public function canTransfer(int $amount): bool
    {
        return $this->balanceInt > $amount;
    }

    public function isSuspended(): bool
    {
        return $this->suspended_state->equals(Activated::class);
    }
}
