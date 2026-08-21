<?php

namespace App\Support;

/**
 * ฟังก์ชันช่วยเหลือสำหรับข้อมูลภาษาไทย / ตัวเลข / รหัสพื้นที่
 * (ย้ายมาจากฟังก์ชัน fmt, nrm, dedup, tamName, tamCode ในไฟล์ HTML ต้นฉบับ)
 */
class Thai
{
    /** สระ/วรรณยุกต์ที่มักพิมพ์ซ้ำติดกัน */
    private const MARKS = '\x{0E31}-\x{0E3A}\x{0E47}-\x{0E4E}';

    /** คำนำหน้าชื่อที่ถูกรูป */
    public const PREFIXES = ['นาย', 'นาง', 'นางสาว', 'เด็กชาย', 'เด็กหญิง'];

    /** ชื่อหมู่บ้านมาตรฐาน (ชุดข้อมูลอ้างอิง) */
    public const VILLAGES = ['โฉลง', 'ปุโรง', 'ตะโละปานะ', 'ลูโบ๊ะกาโล', 'น้ำเย็น'];

    /** จัดรูปแบบตัวเลขแบบมีคอมมา — คืนค่าว่างถ้าเป็น null */
    public static function fmt(int|float|string|null $n): string
    {
        if ($n === null || $n === '') {
            return '';
        }

        return number_format((float) $n, 0, '.', ',');
    }

    /** ย่อจำนวนเงินหลักล้าน เช่น 25,437,900 → 25.44 ล. */
    public static function compact(int|float|null $n): string
    {
        if ($n === null) {
            return '';
        }

        if ($n >= 1_000_000) {
            return rtrim(rtrim(number_format($n / 1_000_000, 2, '.', ','), '0'), '.').' ล.';
        }

        return self::fmt(round($n));
    }

    /** ตัดช่องว่างและปรับการสะกดอำเภอให้ตรงกันก่อนเปรียบเทียบ */
    public static function nrm(?string $s): string
    {
        $s = preg_replace('/\s+/u', '', trim((string) $s));

        return str_replace('กรงปีนัง', 'กรงปินัง', (string) $s);
    }

    /** ตัดสระ/วรรณยุกต์ที่พิมพ์ซ้ำติดกันออก เช่น "ปุุโรง" → "ปุโรง" */
    public static function dedup(?string $s): string
    {
        return (string) preg_replace('/(['.self::MARKS.'])\1+/u', '$1', trim((string) $s));
    }

    /** true ถ้าพบสระ/วรรณยุกต์ซ้ำติดกัน */
    public static function hasDoubleMark(?string $s): bool
    {
        return (bool) preg_match('/(['.self::MARKS.'])\1/u', (string) $s);
    }

    /** "ปุโรง(10)" → "ปุโรง" */
    public static function tamName(?string $t): string
    {
        return (string) preg_replace('/\(\d+\)$/u', '', (string) $t);
    }

    /** "ปุโรง(10)" → 10 */
    public static function tamCode(?string $t): ?int
    {
        return preg_match('/\((\d+)\)$/u', (string) $t, $m) ? (int) $m[1] : null;
    }

    /** "2568-11-04" → "04/11/2568" */
    public static function date(?string $iso): string
    {
        if (! $iso) {
            return '';
        }

        [$y, $m, $d] = array_pad(explode('-', $iso), 3, '');

        return "$d/$m/$y";
    }

    /** เปรียบเทียบข้อความแบบภาษาไทย (ใช้ intl ถ้ามี) */
    public static function compare(?string $a, ?string $b): int
    {
        static $collator = null;

        if ($collator === null) {
            $collator = class_exists(\Collator::class) ? new \Collator('th_TH') : false;
        }

        return $collator
            ? (int) $collator->compare((string) $a, (string) $b)
            : strcmp((string) $a, (string) $b);
    }

    /** แยกชื่อเป็น [คำนำหน้า, ชื่อ, นามสกุล] */
    public static function splitName(string $name): array
    {
        /* เทียบคำนำหน้าที่ยาวที่สุดก่อน เพื่อไม่ให้ «นางสาว» ถูกตัดเป็น «นาง» */
        $prefixes = self::PREFIXES;
        usort($prefixes, fn ($a, $b) => mb_strlen($b) <=> mb_strlen($a));

        foreach ($prefixes as $p) {
            if (str_starts_with($name, $p)) {
                $rest = preg_split('/\s+/u', trim(mb_substr($name, mb_strlen($p))));

                return [$p, $rest[0] ?? '', implode(' ', array_slice($rest, 1))];
            }
        }

        $w = preg_split('/\s+/u', trim($name));

        return ['', $w[0] ?? '', implode(' ', array_slice($w, 1))];
    }

    /**
     * ตรวจเลขประจำตัวประชาชนไทย 13 หลัก (รวมหลักตรวจสอบตัวสุดท้าย)
     * วิธีคิด: นำ 12 หลักแรกคูณ 13,12,...,2 รวมกัน แล้ว (11 - ผลรวม % 11) % 10 = หลักที่ 13
     */
    public static function isValidCitizenId(?string $id): bool
    {
        $digits = preg_replace('/\D/', '', (string) $id);

        if (strlen((string) $digits) !== 13) {
            return false;
        }

        $sum = 0;

        for ($i = 0; $i < 12; $i++) {
            $sum += (int) $digits[$i] * (13 - $i);
        }

        return (11 - $sum % 11) % 10 === (int) $digits[12];
    }

    /** 1234567890123 → 1-2345-67890-12-3 */
    public static function formatCitizenId(?string $id): string
    {
        $digits = preg_replace('/\D/', '', (string) $id);

        if (strlen((string) $digits) !== 13) {
            return (string) $id;
        }

        return preg_replace('/^(\d)(\d{4})(\d{5})(\d{2})(\d)$/', '$1-$2-$3-$4-$5', $digits);
    }

    /** คำนำหน้าชื่อถูกรูปหรือไม่ */
    public static function hasValidPrefix(string $name): bool
    {
        foreach (self::PREFIXES as $p) {
            if (str_starts_with($name, $p)) {
                return true;
            }
        }

        return false;
    }
}
