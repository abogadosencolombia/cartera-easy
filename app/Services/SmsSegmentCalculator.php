<?php

namespace App\Services;

use InvalidArgumentException;

class SmsSegmentCalculator
{
    private const GSM_BASIC = "@£\$¥èéùìòÇ\nØø\rÅåΔ_ΦΓΛΩΠΨΣΘΞÆæßÉ !\"#¤%&'()*+,-./0123456789:;<=>?¡ABCDEFGHIJKLMNOPQRSTUVWXYZÄÖÑÜ§¿abcdefghijklmnopqrstuvwxyzäöñüà";

    private const GSM_EXTENSION = '^{}\\[~]|€';

    /**
     * @return array{encoding: string, units: int, segments: int, per_segment_limit: int}
     */
    public function calculate(string $message): array
    {
        if ($message === '') {
            throw new InvalidArgumentException('El mensaje SMS no puede estar vacío.');
        }

        $gsmUnits = $this->gsmUnits($message);

        if ($gsmUnits !== null) {
            $limit = $gsmUnits <= 160 ? 160 : 153;

            return [
                'encoding' => 'GSM-7',
                'units' => $gsmUnits,
                'segments' => (int) ceil($gsmUnits / $limit),
                'per_segment_limit' => $limit,
            ];
        }

        $units = (int) (strlen(mb_convert_encoding($message, 'UTF-16BE', 'UTF-8')) / 2);
        $limit = $units <= 70 ? 70 : 67;

        return [
            'encoding' => 'UCS-2',
            'units' => $units,
            'segments' => (int) ceil($units / $limit),
            'per_segment_limit' => $limit,
        ];
    }

    private function gsmUnits(string $message): ?int
    {
        $units = 0;

        foreach (mb_str_split($message) as $character) {
            if (mb_strpos(self::GSM_BASIC, $character) !== false) {
                $units++;

                continue;
            }

            if (mb_strpos(self::GSM_EXTENSION, $character) !== false) {
                $units += 2;

                continue;
            }

            return null;
        }

        return $units;
    }
}
