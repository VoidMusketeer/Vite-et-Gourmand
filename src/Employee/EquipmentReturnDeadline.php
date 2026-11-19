<?php
declare(strict_types=1);
namespace App\Employee;
final class EquipmentReturnDeadline
{
    // Metropolitan France: Monday-Friday excluding national public holidays.
    public function after(\DateTimeImmutable $start): \DateTimeImmutable
    {
        $date = $start
            ->setTimezone(new \DateTimeZone("Europe/Paris"))
            ->setTime(0, 0);
        for ($days = 0; $days < 10; ) {
            $date = $date->modify("+1 day");
            if ((int) $date->format("N") < 6 && !$this->holiday($date)) {
                ++$days;
            }
        }
        return $date;
    }
    private function holiday(\DateTimeImmutable $date): bool
    {
        if (
            in_array(
                $date->format("m-d"),
                [
                    "01-01",
                    "05-01",
                    "05-08",
                    "07-14",
                    "08-15",
                    "11-01",
                    "11-11",
                    "12-25",
                ],
                true,
            )
        ) {
            return true;
        }
        $year = (int) $date->format("Y");
        // Gregorian Easter (Meeus/Jones/Butcher), independent of PHP calendar extension.
        $a = $year % 19;
        $b = intdiv($year, 100);
        $c = $year % 100;
        $d = intdiv($b, 4);
        $e = $b % 4;
        $f = intdiv($b + 8, 25);
        $g = intdiv($b - $f + 1, 3);
        $h = (19 * $a + $b - $d - $g + 15) % 30;
        $i = intdiv($c, 4);
        $k = $c % 4;
        $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
        $m = intdiv($a + 11 * $h + 22 * $l, 451);
        $month = intdiv($h + $l - 7 * $m + 114, 31);
        $day = (($h + $l - 7 * $m + 114) % 31) + 1;
        $easter = $date->setDate($year, $month, $day);
        foreach ([1, 39, 50] as $offset) {
            if (
                $date->format("Y-m-d") ===
                $easter->modify("+" . $offset . " days")->format("Y-m-d")
            ) {
                return true;
            }
        }
        return false;
    }
}
