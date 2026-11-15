<?php
declare(strict_types=1);

final class BookingRules
{
    /** Validate a session slot and return normalized date/time boundaries. */
    public static function validateSlot(string $date, string $time, int $durationMinutes, ?DateTimeImmutable $now = null): array
    {
        $now ??= new DateTimeImmutable('now');
        $dateObject = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        if (!$dateObject || $dateObject->format('Y-m-d') !== $date || $dateObject < $now->setTime(0, 0)) {
            throw new InvalidArgumentException('Tanggal sesi tidak valid atau sudah lewat.');
        }
        if (!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $time)) {
            throw new InvalidArgumentException('Jam sesi tidak valid.');
        }
        if ($durationMinutes < 30 || $durationMinutes > 360) {
            throw new InvalidArgumentException('Durasi sesi berada di luar batas sistem.');
        }
        $start = new DateTimeImmutable($date . ' ' . $time, $now->getTimezone());
        $end = $start->modify('+' . $durationMinutes . ' minutes');
        if ($start->format('H:i') < '09:00' || $end->format('H:i') > '18:00' || $end->format('Y-m-d') !== $date) {
            throw new InvalidArgumentException('Jam pemotretan harus dimulai paling cepat 09.00 dan selesai paling lambat 18.00.');
        }
        if ($date === $now->format('Y-m-d') && $start <= $now) {
            throw new InvalidArgumentException('Jam sesi pada hari ini harus berada di waktu mendatang.');
        }
        return ['date' => $date, 'time' => $time, 'time_sql' => $time . ':00', 'start' => $start, 'end' => $end, 'duration' => $durationMinutes];
    }

    public static function overlaps(DateTimeImmutable $startA, int $durationA, DateTimeImmutable $startB, int $durationB): bool
    {
        $endA = $startA->modify('+' . $durationA . ' minutes');
        $endB = $startB->modify('+' . $durationB . ' minutes');
        return $startA < $endB && $startB < $endA;
    }

    public static function minimumFirstPayment(float $packagePrice): int
    {
        return (int) ceil(max(0, $packagePrice) * 0.30);
    }

    public static function paymentStatus(float $approved, float $pending, float $packagePrice): string
    {
        if ($approved + 0.001 >= $packagePrice) return 'lunas';
        if ($approved > 0) return 'dp';
        if ($pending > 0) return 'menunggu';
        return 'belum_bayar';
    }
}
