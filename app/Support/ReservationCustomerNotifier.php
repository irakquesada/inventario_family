<?php

namespace App\Support;

use App\Models\Reservation;
use App\Notifications\CustomerReservationNotification;
use Illuminate\Support\Facades\Notification;

class ReservationCustomerNotifier
{
    public function send(Reservation $reservation, string $state): void
    {
        if (! $reservation->customer_email) {
            return;
        }

        Notification::route('mail', [$reservation->customer_email => $reservation->customer_name])
            ->notify(new CustomerReservationNotification($reservation, $state));
    }
}
