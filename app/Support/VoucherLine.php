<?php

namespace App\Support;

/**
 * One line of the voucher being written: values placed at a column, or right-aligned to end on one
 */
final class VoucherLine
{
    private array $rows;

    public function __construct(array &$rows, private float $row)
    {
        $this->rows = &$rows;
        $this->rows[(string) $row] ??= '';
    }

    public function at(int $col, string $value): self
    {
        $text = &$this->rows[(string) $this->row];
        $text = $text . str_repeat(' ', max(0, $col - mb_strlen($text)));
        $text = mb_substr($text, 0, $col) . $value . mb_substr($text, $col + mb_strlen($value));

        return $this;
    }

    /** Right-aligned, its last character on column $end */
    public function to(int $end, string $value): self
    {
        return $value === '' ? $this : $this->at($end - mb_strlen($value) + 1, $value);
    }
}
