<?php

namespace Tests\Unit\Probability;

use App\Domain\Probability\PercentagePpmConverter;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class PercentagePpmConverterTest extends TestCase
{
    public function validPercentagesProvider()
    {
        return [
            ['0', 0, '0.0000'],
            ['0.0001', 1, '0.0001'],
            ['0.001', 10, '0.0010'],
            ['0.005', 50, '0.0050'],
            ['0.0050', 50, '0.0050'],
            ['0.01', 100, '0.0100'],
            ['0.1', 1000, '0.1000'],
            ['0.0123', 123, '0.0123'],
            ['1', 10000, '1.0000'],
            ['12.3456', 123456, '12.3456'],
            ['99.9999', 999999, '99.9999'],
            ['100', 1000000, '100.0000'],
            ['100.0000', 1000000, '100.0000'],
        ];
    }

    /**
     * @dataProvider validPercentagesProvider
     */
    public function test_converts_strict_percentage_strings_without_floats($percentage, $expectedPpm, $expectedPercentage)
    {
        $converter = new PercentagePpmConverter();

        $this->assertSame($expectedPpm, $converter->toPpm($percentage));
        $this->assertSame($expectedPercentage, $converter->toPercentageString($expectedPpm));
    }

    public function invalidPercentagesProvider()
    {
        return [[''], ['-1'], ['100.0001'], ['0.00001'], ['1.23456'], ['01'], ['1e-4'], ['0,1'], [' texto'], ['1 '], ['texto']];
    }

    /**
     * @dataProvider invalidPercentagesProvider
     */
    public function test_rejects_ambiguous_or_out_of_range_formats($percentage)
    {
        $this->expectException(InvalidArgumentException::class);
        (new PercentagePpmConverter())->toPpm($percentage);
    }
}
