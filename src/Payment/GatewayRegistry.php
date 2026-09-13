<?php

declare(strict_types=1);

namespace Mt2Cms\Payment;

final class GatewayRegistry
{
    /** @var array<string, PaymentGateway> */
    private array $gateways = [];

    public function register(PaymentGateway $gateway): void
    {
        $this->gateways[$gateway->id()] = $gateway;
    }

    public function get(string $id): PaymentGateway
    {
        if (!isset($this->gateways[$id])) {
            throw new \InvalidArgumentException('payments.unknown_gateway');
        }

        return $this->gateways[$id];
    }

    public function has(string $id): bool
    {
        return isset($this->gateways[$id]);
    }

    /**
     * @return list<PaymentGateway>
     */
    public function all(): array
    {
        return array_values($this->gateways);
    }

    /**
     * @return list<PaymentGateway>
     */
    public function configured(): array
    {
        return array_values(array_filter(
            $this->gateways,
            static fn (PaymentGateway $g): bool => $g->configured(),
        ));
    }

    public function active(): ?PaymentGateway
    {
        foreach ($this->configured() as $gateway) {
            return $gateway;
        }

        return null;
    }
}
