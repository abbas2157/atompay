<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\QuoteRequest as WebQuoteRequest;
use App\Services\InstalmentQuoteService;
use Illuminate\Validation\Validator;

/**
 * The web quote endpoint reports an unavailable tenure or out-of-range
 * down payment as one generic message. The app gets them as field errors
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

            $quotes = app(InstalmentQuoteService::class);
            $tenures = $quotes->tenures();

            if (! in_array((int) $this->months, $tenures, true)) {
                $validator->errors()->add('months', 'Choose one of: '.implode(', ', $tenures).' months.');
            }

            if ($this->filled('advance')) {
                $bounds = $quotes->advanceBounds((int) $this->price);
                if ($this->advance < $bounds['min'] || $this->advance > $bounds['max']) {
                    $validator->errors()->add('advance', sprintf(
                        'Down payment must be between PKR %s and PKR %s.',
                        number_format($bounds['min']), number_format($bounds['max']),
                    ));
                }
            }
        }];
    }
}
