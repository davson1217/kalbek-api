<?php

namespace App;

enum CefrLevel: string
{
    case PreA1 = 'pre_a1';
    case A1 = 'a1';
    case A2 = 'a2';
    case B1 = 'b1';
    case B2 = 'b2';
    case C1 = 'c1';
    case C2 = 'c2';

    public function label(): string
    {
        return match ($this) {
            self::PreA1 => 'Pre-A1',
            self::A1 => 'A1',
            self::A2 => 'A2',
            self::B1 => 'B1',
            self::B2 => 'B2',
            self::C1 => 'C1',
            self::C2 => 'C2',
        };
    }

    public function rank(): int
    {
        return array_search($this, self::ordered(), true);
    }

    public function next(): self
    {
        return self::ordered()[min($this->rank() + 1, count(self::ordered()) - 1)];
    }

    public function previous(): self
    {
        return self::ordered()[max($this->rank() - 1, 0)];
    }

    /**
     * @return array<int, self>
     */
    public static function ordered(): array
    {
        return [self::PreA1, self::A1, self::A2, self::B1, self::B2, self::C1, self::C2];
    }

    public static function estimateFromScore(int $score): self
    {
        return match (true) {
            $score < 40 => self::PreA1,
            $score < 60 => self::A1,
            $score < 75 => self::A2,
            $score < 85 => self::B1,
            $score < 92 => self::B2,
            $score < 97 => self::C1,
            default => self::C2,
        };
    }
}
