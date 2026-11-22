<?php

declare(strict_types=1);

namespace App\Mail\OpenPgp;

use function base64_encode;
use function chunk_split;
use function ltrim;
use function ord;
use function pack;
use function strlen;

/**
 * The framing of RFC 9580 a packet, a subpacket, a multiprecision integer and the ASCII armor are written in.
 */
final readonly class OpenPgpPackets
{
    public const int SIGNATURE = 2;

    public const int PUBLIC_KEY = 6;

    public const int USER_ID = 13;

    /**
     * RFC 9580 §4.2.1, the OpenPGP format of a packet header.
     */
    public static function packet(int $tag, string $body): string
    {
        $length = strlen($body);
        if ($length < 192) {
            $header = pack('C', $length);
        } elseif ($length < 8384) {
            $header = pack('n', $length - 192 + (192 << 8));
        } else {
            $header = "\xFF" . pack('N', $length);
        }

        return pack('C', 0xC0 | $tag) . $header . $body;
    }

    /**
     * RFC 9580 §5.2.3.1: every subpacket this provider writes is shorter than 191 octets.
     */
    public static function subpacket(int $type, string $data): string
    {
        return pack('CC', strlen($data) + 1, $type) . $data;
    }

    /**
     * RFC 9580 §3.2: the length in bits, then the octets, big-endian, without a leading zero.
     */
    public static function mpi(string $octets): string
    {
        $octets = ltrim($octets, "\0");
        if ($octets === '') {
            return "\0\0";
        }
        $bits = (strlen($octets) - 1) * 8;
        for ($first = ord($octets[0]); $first > 0; $first >>= 1) {
            ++$bits;
        }

        return pack('n', $bits) . $octets;
    }

    /**
     * RFC 9580 §6.2, without the CRC24 footer §6.1 says not to write.
     *
     * @param string $label "PUBLIC KEY BLOCK" or "SIGNATURE"
     */
    public static function armor(string $label, string $binary): string
    {
        return '-----BEGIN PGP ' . $label . "-----\n\n" . chunk_split(base64_encode($binary), 64, "\n") . '-----END PGP ' . $label . "-----\n";
    }
}
