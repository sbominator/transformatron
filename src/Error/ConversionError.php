<?php

namespace SBOMinator\Transformatron\Error;

/**
 * Represents an error encountered during SBOM format conversion.
 *
 * This class encapsulates error information including severity, context, and details,
 * allowing for more sophisticated error handling and reporting than simple exceptions.
 */
class ConversionError
{
    /** @var string Error severity level */
    public const SEVERITY_INFO = 'info';
    public const SEVERITY_WARNING = 'warning';
    public const SEVERITY_ERROR = 'error';
    public const SEVERITY_CRITICAL = 'critical';

    /** @var string The error message */
    private string $message;

    /** @var string Error severity level */
    private string $severity;

    /** @var string The component where the error occurred */
    private string $component;

    /** @var array<string, mixed> Additional error context */
    private array $context = [];

    /** @var string|null Error code for categorization */
    private ?string $code;

    /** @var \Throwable|null Original exception if any */
    private ?\Throwable $previous;

    /**
     * Create a new conversion error.
     *
     * @param string $message Error message
     * @param string $severity Error severity level
     * @param string $component Component where the error occurred
     * @param array<string, mixed> $context Additional error context
     * @param string|null $code Error code for categorization
     * @param \Throwable|null $previous Original exception if any
     */
    public function __construct(
        string $message,
        string $severity = self::SEVERITY_ERROR,
        string $component = 'unknown',
        array $context = [],
        ?string $code = null,
        ?\Throwable $previous = null
    ) {
        $this->message = $message;
        $this->severity = $severity;
        $this->component = $component;
        $this->context = $context;
        $this->code = $code;
        $this->previous = $previous;
    }

    /**
     * Get the error message.
     *
     * @return string
     */
    public function getMessage(): string
    {
        return $this->message;
    }

    /**
     * Get the error severity.
     *
     * @return string
     */
    public function getSeverity(): string
    {
        return $this->severity;
    }

    /**
     * Get the component where the error occurred.
     *
     * @return string
     */
    public function getComponent(): string
    {
        return $this->component;
    }

    /**
     * Get additional error context.
     *
     * @return array<string, mixed>
     */
    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * Get a specific context value.
     *
     * @param string $key Context key
     * @param mixed $default Default value if key not found
     * @return mixed
     */
    public function getContextValue(string $key, $default = null)
    {
        return $this->context[$key] ?? $default;
    }

    /**
     * Get the error code.
     *
     * @return string|null
     */
    public function getCode(): ?string
    {
        return $this->code;
    }

    /**
     * Get the original exception.
     *
     * @return \Throwable|null
     */
    public function getPrevious(): ?\Throwable
    {
        return $this->previous;
    }

    /**
     * Check if the error is of specified severity or higher.
     *
     * @param string $severity Minimum severity level to check
     * @return bool
     */
    public function isSeverityOrWorse(string $severity): bool
    {
        $levels = [
            self::SEVERITY_INFO => 0,
            self::SEVERITY_WARNING => 1,
            self::SEVERITY_ERROR => 2,
            self::SEVERITY_CRITICAL => 3
        ];

        return ($levels[$this->severity] ?? 0) >= ($levels[$severity] ?? 0);
    }

    /**
     * Create a warning level error.
     *
     * @param string $message Error message
     * @param string $component Component where the warning occurred
     * @param array<string, mixed> $context Additional error context
     * @param string|null $code Error code
     * @return self
     */
    public static function createWarning(
        string $message,
        string $component = 'unknown',
        array $context = [],
        ?string $code = null
    ): self {
        return new self(
            $message,
            self::SEVERITY_WARNING,
            $component,
            $context,
            $code
        );
    }

    /**
     * Create an error level error.
     *
     * @param string $message Error message
     * @param string $component Component where the error occurred
     * @param array<string, mixed> $context Additional error context
     * @param string|null $code Error code
     * @param \Throwable|null $previous Original exception if any
     * @return self
     */
    public static function createError(
        string $message,
        string $component = 'unknown',
        array $context = [],
        ?string $code = null,
        ?\Throwable $previous = null
    ): self {
        return new self(
            $message,
            self::SEVERITY_ERROR,
            $component,
            $context,
            $code,
            $previous
        );
    }

    /**
     * Create a critical level error.
     *
     * @param string $message Error message
     * @param string $component Component where the critical error occurred
     * @param array<string, mixed> $context Additional error context
     * @param string|null $code Error code
     * @param \Throwable|null $previous Original exception if any
     * @return self
     */
    public static function createCritical(
        string $message,
        string $component = 'unknown',
        array $context = [],
        ?string $code = null,
        ?\Throwable $previous = null
    ): self {
        return new self(
            $message,
            self::SEVERITY_CRITICAL,
            $component,
            $context,
            $code,
            $previous
        );
    }

    /**
     * Create an info level message.
     *
     * @param string $message Info message
     * @param string $component Component where the info originated
     * @param array<string, mixed> $context Additional message context
     * @param string|null $code Message code
     * @return self
     */
    public static function createInfo(
        string $message,
        string $component = 'unknown',
        array $context = [],
        ?string $code = null
    ): self {
        return new self(
            $message,
            self::SEVERITY_INFO,
            $component,
            $context,
            $code
        );
    }

    /**
     * Convert the error to a string representation.
     *
     * @return string
     */
    public function __toString(): string
    {
        $contextStr = empty($this->context) ? '' : ' [Context: ' . json_encode($this->context) . ']';
        $codeStr = $this->code ? " (Code: {$this->code})" : '';

        return "[{$this->severity}] {$this->component}: {$this->message}{$codeStr}{$contextStr}";
    }
}