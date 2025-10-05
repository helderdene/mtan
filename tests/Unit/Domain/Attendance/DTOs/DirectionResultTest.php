<?php

namespace Tests\Unit\Domain\Attendance\DTOs;

use App\Domain\Attendance\DTOs\DirectionResult;
use PHPUnit\Framework\TestCase;

class DirectionResultTest extends TestCase
{
    public function test_can_create_direction_result_with_valid_data(): void
    {
        $result = new DirectionResult(
            direction: 'check-in',
            confidence: 85,
            scores: [
                'last_record' => 30,
                'shift_timing' => 35,
                'work_duration' => 0,
                'fallback' => 20,
            ],
            reason: 'High confidence: Near shift start, last action was check-out'
        );

        $this->assertEquals('check-in', $result->direction);
        $this->assertEquals(85, $result->confidence);
        $this->assertIsArray($result->scores);
        $this->assertCount(4, $result->scores);
        $this->assertEquals('High confidence: Near shift start, last action was check-out', $result->reason);
    }

    public function test_validates_direction_is_valid_enum(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid direction');

        new DirectionResult(
            direction: 'invalid-direction',
            confidence: 85,
            scores: [],
            reason: 'Test'
        );
    }

    public function test_accepts_all_valid_directions(): void
    {
        $validDirections = ['check-in', 'check-out', 'break-start', 'break-end'];

        foreach ($validDirections as $direction) {
            $result = new DirectionResult(
                direction: $direction,
                confidence: 50,
                scores: [],
                reason: 'Test'
            );

            $this->assertEquals($direction, $result->direction);
        }
    }

    public function test_validates_confidence_is_between_0_and_100(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Confidence must be between 0 and 100');

        new DirectionResult(
            direction: 'check-in',
            confidence: 150,
            scores: [],
            reason: 'Test'
        );
    }

    public function test_accepts_confidence_at_boundaries(): void
    {
        $resultZero = new DirectionResult(
            direction: 'check-in',
            confidence: 0,
            scores: [],
            reason: 'Test'
        );
        $this->assertEquals(0, $resultZero->confidence);

        $resultHundred = new DirectionResult(
            direction: 'check-in',
            confidence: 100,
            scores: [],
            reason: 'Test'
        );
        $this->assertEquals(100, $resultHundred->confidence);
    }

    public function test_rejects_negative_confidence(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Confidence must be between 0 and 100');

        new DirectionResult(
            direction: 'check-in',
            confidence: -10,
            scores: [],
            reason: 'Test'
        );
    }

    public function test_to_array_returns_all_properties(): void
    {
        $result = new DirectionResult(
            direction: 'check-out',
            confidence: 92,
            scores: [
                'last_record' => 25,
                'shift_timing' => 35,
                'work_duration' => 15,
                'fallback' => 17,
            ],
            reason: 'High confidence: Near shift end'
        );

        $array = $result->toArray();

        $this->assertIsArray($array);
        $this->assertArrayHasKey('direction', $array);
        $this->assertArrayHasKey('confidence', $array);
        $this->assertArrayHasKey('scores', $array);
        $this->assertArrayHasKey('reason', $array);
        $this->assertEquals('check-out', $array['direction']);
        $this->assertEquals(92, $array['confidence']);
        $this->assertEquals('High confidence: Near shift end', $array['reason']);
    }

    public function test_get_confidence_level_returns_high_for_80_and_above(): void
    {
        $result = new DirectionResult(
            direction: 'check-in',
            confidence: 80,
            scores: [],
            reason: 'Test'
        );

        $this->assertEquals('high', $result->getConfidenceLevel());

        $result95 = new DirectionResult(
            direction: 'check-in',
            confidence: 95,
            scores: [],
            reason: 'Test'
        );

        $this->assertEquals('high', $result95->getConfidenceLevel());
    }

    public function test_get_confidence_level_returns_medium_for_50_to_79(): void
    {
        $result50 = new DirectionResult(
            direction: 'check-in',
            confidence: 50,
            scores: [],
            reason: 'Test'
        );

        $this->assertEquals('medium', $result50->getConfidenceLevel());

        $result65 = new DirectionResult(
            direction: 'check-in',
            confidence: 65,
            scores: [],
            reason: 'Test'
        );

        $this->assertEquals('medium', $result65->getConfidenceLevel());

        $result79 = new DirectionResult(
            direction: 'check-in',
            confidence: 79,
            scores: [],
            reason: 'Test'
        );

        $this->assertEquals('medium', $result79->getConfidenceLevel());
    }

    public function test_get_confidence_level_returns_low_for_below_50(): void
    {
        $result0 = new DirectionResult(
            direction: 'check-in',
            confidence: 0,
            scores: [],
            reason: 'Test'
        );

        $this->assertEquals('low', $result0->getConfidenceLevel());

        $result25 = new DirectionResult(
            direction: 'check-in',
            confidence: 25,
            scores: [],
            reason: 'Test'
        );

        $this->assertEquals('low', $result25->getConfidenceLevel());

        $result49 = new DirectionResult(
            direction: 'check-in',
            confidence: 49,
            scores: [],
            reason: 'Test'
        );

        $this->assertEquals('low', $result49->getConfidenceLevel());
    }

    public function test_properties_are_readonly(): void
    {
        $result = new DirectionResult(
            direction: 'check-in',
            confidence: 85,
            scores: ['test' => 10],
            reason: 'Test'
        );

        // PHP 8.1+ readonly properties will throw an error if we try to modify
        // This test ensures the properties are defined as readonly
        $reflection = new \ReflectionClass($result);

        $this->assertTrue($reflection->getProperty('direction')->isReadOnly());
        $this->assertTrue($reflection->getProperty('confidence')->isReadOnly());
        $this->assertTrue($reflection->getProperty('scores')->isReadOnly());
        $this->assertTrue($reflection->getProperty('reason')->isReadOnly());
    }

    public function test_scores_breakdown_includes_all_factors(): void
    {
        $scores = [
            'last_record' => 30,
            'shift_timing' => 35,
            'work_duration' => 15,
            'fallback' => 20,
        ];

        $result = new DirectionResult(
            direction: 'check-in',
            confidence: 100,
            scores: $scores,
            reason: 'Perfect match'
        );

        $this->assertEquals(30, $result->scores['last_record']);
        $this->assertEquals(35, $result->scores['shift_timing']);
        $this->assertEquals(15, $result->scores['work_duration']);
        $this->assertEquals(20, $result->scores['fallback']);
    }
}
