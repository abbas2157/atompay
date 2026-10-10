<?php

namespace App\Services;

use App\Exceptions\Api\BusinessRuleException;
use App\Mail\AccountDeletedMail;
use App\Models\CreditAssessment;
use App\Models\CustomerNotification;
use App\Models\Enums\OrderStatus;
use App\Models\Order;
use App\Models\PasswordReset;
use App\Models\PendingSignup;
use App\Models\User;
use App\Models\UserPreference;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Self-service account deletion, required by Google Play and the App Store.
 * Used by `POST /me/delete` and the website's /my/delete page.
 *
 * What goes: sign-ins (every token, push device, web session), the KYC
 * profile and its documents, the inbox, preferences, reset and sign-up
 * records, and - for a customer who never placed an order - the income
 * answers. The shared AtomShop account is blocked and its name, email,
 * phone, password and customer details are overwritten, so the email and
 * number are free to register again.
 *
 * What stays: orders, instalment schedules and payments, and the credit
 * decisions behind any order (minus the employer name). They keep pointing
 * at the anonymised `users` row, which is all that ties them to a person.
 */
class AccountDeletionService
{
    public const REASON = 'self-service deletion';

    /** Refuses while money is owed or an order is waiting for approval. */
    public function ensureDeletable(User $user): void
    {
        $orders = Order::query()->where('user_id', $user->id);

        if ((clone $orders)->whereIn('status', [OrderStatus::Pending, OrderStatus::Verification])->exists()) {
            throw new BusinessRuleException('outstanding_balance',
                'You have an order waiting for approval. You can delete your account once it is completed or cancelled.');
        }

        // An active order with no schedule yet still has everything to pay.
        $owing = $orders->whereIn('status', OrderStatus::active())
            ->where(fn (Builder $q) => $q
                ->whereHas('instalments', fn (Builder $i) => $i->unpaid()->where('order_instalments.user_id', $user->id))
                ->orWhereHas('mirrorInstalments', fn (Builder $i) => $i->unpaid()->where('order_instalments.user_id', $user->id))
                ->orWhere(fn (Builder $none) => $none->doesntHave('instalments')->doesntHave('mirrorInstalments')))
            ->exists();

        if ($owing) {
            throw new BusinessRuleException('outstanding_balance',
                'You still have instalments to pay. You can delete your account once every plan is repaid.');
        }
    }

    /**
     * Deletes and anonymises in one transaction; documents are removed from
     * disk only once it has committed, so a rollback never loses a file.
     *
     * @param string $source app | web
     */
    public function delete(User $user, string $source, ?string $ip): void
    {
        $this->ensureDeletable($user);

        // Captured before the row is overwritten - the goodbye goes to the real address.
        $name = $user->shortName();
        $email = $user->hasRealEmail() ? $user->email : null;

        $profile = $user->kycProfile;
        $documents = $profile
            ? array_filter([$profile->cnic_front_path, $profile->cnic_back_path, $profile->selfie_path, $profile->verification_form_path])
            : [];

        DB::transaction(function () use ($user, $profile, $source, $ip) {
            // 1. Every sign-in, on every device and on both sites.
            $user->tokens()->delete();
            $user->devices()->delete();
            DB::table('personal_access_tokens')
                ->where('tokenable_type', User::class)->where('tokenable_id', $user->id)->delete();
            DB::table('sessions')->where('user_id', $user->id)->delete();
            DB::table('fcm_tokens')->where('user_id', $user->id)->delete();
            DB::table('verify_codes')->where('user_id', $user->id)->delete();

            // 2. AtomPay personal data with no legal reason to keep it.
            $profile?->delete();
            CustomerNotification::where('user_id', $user->id)->delete();
            UserPreference::where('user_id', $user->id)->delete();
            PasswordReset::where('user_id', $user->id)->delete();
            PendingSignup::where('user_id', $user->id)->delete();

            if (Order::where('user_id', $user->id)->exists()) {
                // Kept: the decision behind a financed order. Who employs them is not needed for that.
                CreditAssessment::where('user_id', $user->id)->update(['employer_name' => null]);
            } else {
                CreditAssessment::where('user_id', $user->id)->delete();
            }

            // 3. The shared AtomShop account: blocked everywhere, nothing left that names them.
            $user->forceFill([
                'name' => 'Deleted user',
                'email' => "deleted+{$user->id}@deleted.invalid",
                'phone' => "deleted-{$user->id}",
                'password' => Str::random(64),             // hashed by the model cast
                'remember_token' => Str::random(60),
                'status' => 'block',
                'email_verified_at' => null,
                'user_ip' => null,
                'last_login_from' => null,
            ])->save();

            DB::table('customers')->where('user_id', $user->id)->update([
                'picture' => null, 'address' => null, 'cnic_no' => null,
                'father_name' => null, 'residence_phone' => null, 'office_address' => null,
                'office_phone' => null, 'alternate_phone' => null, 'not_verified_reason' => null,
                'updated_at' => now(),
            ]);

            // 5. Audit, without the PII.
            DB::table('atompay_account_deletions')->insert([
                'user_id' => $user->id, 'source' => $source, 'reason' => self::REASON,
                'ip' => $ip, 'created_at' => now(),
            ]);
        });

        $disk = Storage::disk(config('atompay.kyc.disk'));
        foreach ($documents as $path) {
            try {
                $disk->delete($path);
            } catch (Throwable $e) {
                Log::error('AtomPay deletion: document not removed', ['user_id' => $user->id, 'path' => $path, 'error' => $e->getMessage()]);
            }
        }

        if ($email) {
            try {
                Mail::to($email)->queue(new AccountDeletedMail($name));
            } catch (Throwable $e) {
                Log::error('AtomPay deletion email failed', ['user_id' => $user->id, 'error' => $e->getMessage()]);
            }
        }
    }
}
