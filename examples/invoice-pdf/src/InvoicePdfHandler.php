<?php

declare(strict_types=1);

namespace App;

use Dompdf\Dompdf;
use RuntimeException;
use Tomaj\Hermes\Handler\RetryableHandlerInterface;
use Tomaj\Hermes\Handler\RetryTrait;
use Tomaj\Hermes\MessageInterface;

final class InvoicePdfHandler implements RetryableHandlerInterface
{
    use RetryTrait;

    public function __construct(private string $directory) {}

    #[\Override]
    public function handle(MessageInterface $message): bool
    {
        $orderId = (int) ($message->getPayload()['order_id'] ?? 0);
        $target = "{$this->directory}/invoice-{$orderId}.pdf";

        // A retry or a duplicate message must not create a second invoice.
        if (is_file($target)) {
            return true;
        }

        $order = $this->loadOrder($orderId);

        $dompdf = new Dompdf();
        $dompdf->loadHtml($this->render($order));
        $dompdf->setPaper('A4');
        $dompdf->render();

        // Write to a temporary file first, so a crash never leaves half a PDF behind.
        $tmp = $target . '.tmp';
        if (file_put_contents($tmp, $dompdf->output()) === false || !rename($tmp, $target)) {
            throw new RuntimeException("Cannot write {$target}");
        }

        return true;
    }

    /** @return array{id: int, customer: string, lines: list<array{0: string, 1: float}>} */
    private function loadOrder(int $orderId): array
    {
        // Replace with a database query.
        return ['id' => $orderId, 'customer' => 'Jane Doe', 'lines' => [['Hosting, October', 19.0], ['Domain', 12.5]]];
    }

    /** @param array{id: int, customer: string, lines: list<array{0: string, 1: float}>} $order */
    private function render(array $order): string
    {
        $rows = '';
        $total = 0.0;
        foreach ($order['lines'] as [$item, $price]) {
            $rows .= sprintf('<tr><td>%s</td><td style="text-align:right">%.2f EUR</td></tr>', htmlspecialchars($item), $price);
            $total += $price;
        }

        return sprintf(
            '<h1>Invoice %d</h1><p>%s</p><table width="100%%">%s<tr><th>Total</th><th style="text-align:right">%.2f EUR</th></tr></table>',
            $order['id'],
            htmlspecialchars($order['customer']),
            $rows,
            $total,
        );
    }
}
