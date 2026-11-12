<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\QuoteRequest as WebQuoteRequest;
use App\Services\InstalmentQuoteService;
use Illuminate\Validation\Validator;

/**
 * The web quote endpoint reports a tenure other than AtomPay's, or any
 * down payment, as one generic message. The app gets them as field errors
 * on `months` / `advance`, so it can show them next to the right control.
 */
class QuoteRequest extends WebQuoteRequest
{
    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            // AtomPay's plan: one fixed term, no advance.
            $tenures = app(InstalmentQuoteService::class)->atompayTenures();

            if (! in_array((int) $this->months, $tenures, true)) {
                $validator->errors()->add('months', InstalmentQuoteService::atompayTermMessage());
            }

            if ($this->filled('advance') && (int) $this->advance !== 0) {
                $validator->errors()->add('advance', InstalmentQuoteService::NO_ADVANCE_MESSAGE);
            }
        }];
    }
}
