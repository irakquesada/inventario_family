<?php

namespace App\Support;

use App\Models\Reservation;

class ReservationMailTemplates
{
    public const RECEIVED = 'Hemos recibido tu solicitud de reserva {{reference}} para el {{date}} a las {{time}}, para {{party_size}} personas. Te avisaremos cuando el establecimiento la confirme.';

    public const CONFIRMED = 'Tu reserva {{reference}} está confirmada para el {{date}} a las {{time}}, para {{party_size}} personas. ¡Te esperamos!';

    public const CANCELLED = 'No hemos podido mantener la reserva {{reference}} del {{date}} a las {{time}}. Si necesitas otra fecha, contacta con nosotros y te ayudaremos.';

    public static function default(string $status): string
    {
        return match ($status) {
            'received' => self::RECEIVED,
            'confirmed' => self::CONFIRMED,
            'cancelled' => self::CANCELLED,
            default => self::RECEIVED,
        };
    }

    public static function render(string $template, Reservation $reservation): string
    {
        return strtr($template, [
            '{{customer_name}}' => $reservation->customer_name,
            '{{reference}}' => $reservation->reference,
            '{{date}}' => $reservation->starts_at->format('d/m/Y'),
            '{{time}}' => $reservation->starts_at->format('H:i'),
            '{{party_size}}' => (string) $reservation->party_size,
            '{{resource}}' => $reservation->resource?->name ?? '',
            '{{business_name}}' => config('brand.name'),
        ]);
    }
}
