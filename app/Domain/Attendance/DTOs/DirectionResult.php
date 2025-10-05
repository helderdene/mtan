<?php

namespace App\Domain\Attendance\DTOs;

/**
 * Data Transfer Object for Direction Detection Result
 *
 * Encapsulates the result of automatic direction detection with confidence scoring.
 */
readonly class DirectionResult
{
    private const VALID_DIRECTIONS = ['check-in', 'check-out', 'break-start', 'break-end'];

    /**
     * Create a new DirectionResult instance
     *
     * @param string $direction The detected direction (check-in, check-out, break-start, break-end)
     * @param int $confidence Confidence score (0-100)
     * @param array $scores Breakdown of scoring factors
     * @param string $reason Human-readable explanation
     * @throws \InvalidArgumentException
     */
    public function __construct(
        public string $direction,
        public int $confidence,
        public array $scores,
        public string $reason
    ) {
        $this->validateDirection($direction);
        $this->validateConfidence($confidence);
    }

    /**
     * Validate that direction is one of the allowed values
     *
     * @throws \InvalidArgumentException
     */
    private function validateDirection(string $direction): void
    {
        if (!in_array($direction, self::VALID_DIRECTIONS)) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Invalid direction "%s". Must be one of: %s',
                    $direction,
                    implode(', ', self::VALID_DIRECTIONS)
                )
            );
        }
    }

    /**
     * Validate that confidence is between 0 and 100
     *
     * @throws \InvalidArgumentException
     */
    private function validateConfidence(int $confidence): void
    {
        if ($confidence < 0 || $confidence > 100) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Confidence must be between 0 and 100, got %d',
                    $confidence
                )
            );
        }
    }

    /**
     * Convert the DTO to an array for logging/debugging
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'direction' => $this->direction,
            'confidence' => $this->confidence,
            'scores' => $this->scores,
            'reason' => $this->reason,
        ];
    }

    /**
     * Get confidence level as a string
     *
     * @return string 'high', 'medium', or 'low'
     */
    public function getConfidenceLevel(): string
    {
        return match (true) {
            $this->confidence >= 80 => 'high',
            $this->confidence >= 50 => 'medium',
            default => 'low',
        };
    }
}
