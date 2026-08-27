<?php

namespace App\Services\Payment;

class PixService
{
    /**
     * Generate EMVCo standard PIX payload ("Copia e Cola").
     */
    public static function generatePayload(
        string $pixKey,
        ?string $name = null,
        ?string $city = null,
        ?float $amount = null,
        ?string $txId = '***'
    ): string {
        $pixKey = trim($pixKey);
        $name = mb_substr(preg_replace('/[^a-zA-Z0-9 ]/', '', $name ?: 'LOJA SAAS'), 0, 25);
        $city = mb_substr(preg_replace('/[^a-zA-Z0-9 ]/', '', $city ?: 'BRASILIA'), 0, 15);
        $txId = preg_replace('/[^a-zA-Z0-9]/', '', $txId ?: '***');

        $gui = self::formatField('00', 'br.gov.bcb.pix');
        $keyField = self::formatField('01', $pixKey);
        $merchantAccount = self::formatField('26', $gui.$keyField);

        $payload = self::formatField('00', '01')
            .$merchantAccount
            .self::formatField('52', '0000')
            .self::formatField('53', '986');

        if ($amount && $amount > 0) {
            $payload .= self::formatField('54', number_format($amount, 2, '.', ''));
        }

        $payload .= self::formatField('58', 'BR')
            .self::formatField('59', strtoupper($name ?: 'MERCHANT'))
            .self::formatField('60', strtoupper($city ?: 'BRASILIA'))
            .self::formatField('62', self::formatField('05', $txId))
            .'6304';

        $crc = self::calculateCRC16($payload);

        return $payload.$crc;
    }

    protected static function formatField(string $id, string $value): string
    {
        $len = str_pad((string) strlen($value), 2, '0', STR_PAD_LEFT);

        return $id.$len.$value;
    }

    protected static function calculateCRC16(string $payload): string
    {
        $polynomial = 0x1021;
        $crc = 0xFFFF;

        for ($i = 0; $i < strlen($payload); $i++) {
            $crc ^= (ord($payload[$i]) << 8);
            for ($j = 0; $j < 8; $j++) {
                if (($crc & 0x8000) !== 0) {
                    $crc = (($crc << 1) ^ $polynomial) & 0xFFFF;
                } else {
                    $crc = ($crc << 1) & 0xFFFF;
                }
            }
        }

        return strtoupper(str_pad(dechex($crc), 4, '0', STR_PAD_LEFT));
    }
}
