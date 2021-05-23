<?php
declare(strict_types=1);

namespace SlopeIt\ClockMock;

use DateTimeZone;
use SlopeIt\ClockMock\DateTimeMock\DateTimeImmutableMock;
use SlopeIt\ClockMock\DateTimeMock\DateTimeMock;

/**
 * Class that provides static utilities to freeze the current system time using the php-uopz extension.
 */
final class ClockMock
{
    private static bool $areMocksActive = false;

    private static ?\DateTimeInterface $frozenDateTime = null;

    /**
     * @return mixed Anything the provided `$callable` returns.
     */
    public static function executeAtFrozenDateTime(\DateTimeInterface $dateTime, \Closure $callable)
    {
        try {
            self::freeze($dateTime);
            return $callable();
        } finally {
            self::reset();
        }
    }

    public static function freeze(\DateTimeInterface $dateTime): void
    {
        self::$frozenDateTime = clone $dateTime;

        self::activateMocksIfNeeded();
    }

    public static function getFrozenDateTime(): ?\DateTimeInterface
    {
        return self::$frozenDateTime;
    }

    /**
     * Removes any mocks on time (i.e. the ones installed by `ClockMock::freeze`).
     */
    public static function reset(): void
    {
        if (!self::$areMocksActive) {
            return;
        }

        uopz_unset_return('time');
        uopz_unset_return('microtime');
        uopz_unset_return('date');
        uopz_unset_return('idate');
        uopz_unset_return('strtotime');
        uopz_unset_return('localtime');
        uopz_unset_return('getdate');
        uopz_unset_return('date_create');
        uopz_unset_return('date_create_immutable');

        uopz_unset_mock(\DateTime::class);
        uopz_unset_mock(\DateTimeImmutable::class);

        self::$areMocksActive = false;
        self::$frozenDateTime = null;
    }

    private static function activateMocksIfNeeded(): void
    {
        if (self::$areMocksActive) {
            return;
        }

        $time_mock = function () {
            return self::$frozenDateTime->getTimestamp();
        };
        uopz_set_return(
            'time',
            fn () => $time_mock(),
            true
        );

        $microtime_mock = function (bool $as_float) {
            // @see https://www.php.net/manual/en/function.microtime.php
            if ($as_float) {
                return (float) self::$frozenDateTime->format('U.u');
            }

            return self::$frozenDateTime->format('0.u U');
        };
        uopz_set_return(
            'microtime',
            fn (bool $as_float = false) => $microtime_mock($as_float),
            true
        );

        $date_mock = function (string $format, ?int $timestamp) {
            return date($format, $timestamp ?? self::$frozenDateTime->getTimestamp());
        };
        uopz_set_return(
            'date',
            fn (string $format, ?int $timestamp = null) => $date_mock($format, $timestamp),
            true,
        );

        $idate_mock = function (string $format, ?int $timestamp) {
            return idate($format, $timestamp ?? self::$frozenDateTime->getTimestamp());
        };
        uopz_set_return(
            'idate',
            fn (string $format, ?int $timestamp = null) => $idate_mock($format, $timestamp),
            true,
        );

        $strtotime_mock = function (string $datetime, ?int $baseTimestamp) {
            return strtotime($datetime, $baseTimestamp ?? self::$frozenDateTime->getTimestamp());
        };
        uopz_set_return(
            'strtotime',
            fn (string $datetime, ?int $baseTimestamp = null) => $strtotime_mock($datetime, $baseTimestamp),
            true,
        );

        $localtime_mock = function (?int $timestamp, bool $associative) {
            return localtime($timestamp ?? self::$frozenDateTime->getTimestamp(), $associative);
        };
        uopz_set_return(
            'localtime',
            fn (?int $timestamp = null, bool $associative = false) => $localtime_mock($timestamp, $associative),
            true,
        );

        $getdate_mock = function (?int $timestamp) {
            return getdate($timestamp ?? self::$frozenDateTime->getTimestamp());
        };
        uopz_set_return(
            'getdate',
            fn (?int $timestamp = null) => $getdate_mock($timestamp),
            true,
        );

        uopz_set_return(
            'date_create',
            fn (?string $datetime = 'now', ?DateTimeZone $timezone = null) => new \DateTime($datetime, $timezone),
            true,
        );

        uopz_set_return(
            'date_create_immutable',
            fn (?string $datetime = 'now', ?DateTimeZone $timezone = null)
                => new \DateTimeImmutable($datetime, $timezone),
            true,
        );

        uopz_set_mock(\DateTime::class, DateTimeMock::class);
        uopz_set_mock(\DateTimeImmutable::class, DateTimeImmutableMock::class);

        self::$areMocksActive = true;
    }
}
