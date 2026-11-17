<?php

namespace Tests\Unit;

use App\Support\PlanterCode;
use PHPUnit\Framework\TestCase;

class PlanterCodeTest extends TestCase
{
    public function test_numeric_codes_become_five_digits_with_leading_zeros(): void
    {
        $this->assertSame('09997', PlanterCode::normalize('9997'));
        $this->assertSame('09997', PlanterCode::normalize('09997'));
        $this->assertSame('09997', PlanterCode::normalize('0009997'));
        $this->assertSame('00733', PlanterCode::normalize(733));
        $this->assertSame('00733', PlanterCode::normalize(' 733 '));
        $this->assertSame('58931', PlanterCode::normalize('58931'));
    }

    public function test_other_codes_are_only_trimmed(): void
    {
        $this->assertSame('123456', PlanterCode::normalize('123456'));
        $this->assertSame('A-12', PlanterCode::normalize(' A-12 '));
        $this->assertSame('', PlanterCode::normalize(null));
    }
}
