<?php

namespace App\Support;

/**
 * Mức độ Bloom: giao diện và RAG service dùng nhãn tiếng Việt,
 * cột cauhoi.mucDoBloom trong CSDL lưu giá trị tiếng Anh theo ERD.
 */
class MucDoBloom
{
    public const BANG = [
        'Nhớ' => 'Remember',
        'Hiểu' => 'Understand',
        'Vận dụng' => 'Apply',
        'Phân tích' => 'Analyze',
        'Đánh giá' => 'Evaluate',
        'Sáng tạo' => 'Create',
    ];

    public static function nhanTiengViet(): array
    {
        return array_keys(self::BANG);
    }

    public static function sangCsdl(string $nhan): string
    {
        return self::BANG[$nhan] ?? 'Chưa xác định';
    }

    public static function sangNhan(?string $giaTri): string
    {
        $nhan = array_search($giaTri, self::BANG, true);

        return $nhan === false ? (string) $giaTri : $nhan;
    }
}
