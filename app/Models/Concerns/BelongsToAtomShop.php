<?php

namespace App\Models\Concerns;

/**
 * Marks a model whose table is owned by the AtomShop application.
 *
 * AtomPay reads these tables and must never rewrite AtomShop's records
 * through them; anything AtomPay owns lives in an `atompay_*` table.
 * Guarding everything keeps a stray ->update() from touching shop data.
 */
trait BelongsToAtomShop
{
    public function initializeBelongsToAtomShop(): void
    {
        $this->guarded = ['*'];
    }
}
