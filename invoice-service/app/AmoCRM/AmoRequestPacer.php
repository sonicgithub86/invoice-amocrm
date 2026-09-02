<?php

declare(strict_types=1);

namespace InvoiceService\AmoCRM;

final class AmoRequestPacer
{
    private const MINIMUM_GAP_MICROSECONDS = 250000;

    private ?int $lastRequestAt = null;

    public function beforeRequest(): void
    {
        $now = hrtime(true);
        if ($this->lastRequestAt !== null) {
            $elapsed = intdiv($now - $this->lastRequestAt, 1000);
            $remaining = self::MINIMUM_GAP_MICROSECONDS - $elapsed;
            if ($remaining > 0) {
                usleep($remaining);
            }
        }
        $this->lastRequestAt = hrtime(true);
    }
}
