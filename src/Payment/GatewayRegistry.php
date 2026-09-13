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
     * Gateways with credentials stored (may still be disabled in admin).
     *
     * @return list<PaymentGateway>
     */
    public function configured(): array
    {
        return array_values(array_filter(
            $this->gateways,
            static fn (PaymentGateway $g): bool => $g->configured(),
        ));
    }

    /**
     * Configured gateways that are enabled for new checkouts.
     *
     * @return list<PaymentGateway>
     */
    public function available(): array
    {
        return array_values(array_filter(
            $this->gateways,
            static fn (PaymentGateway $g): bool => $g->configured() && $g->enabled(),
        ));
    }

    public function active(): ?PaymentGateway
    {
        foreach ($this->available() as $gateway) {
            return $gateway;
        }

        return null;
    }

    /**
     * Resolve an available gateway by id, or the first available one when $id is empty.
     */
    public function resolve(?string $id = null): ?PaymentGateway
    {
        $id = $id !== null ? trim($id) : '';

        if ($id !== '') {
            if (!$this->has($id)) {
                return null;
            }

            $gateway = $this->get($id);

            return $gateway->configured() && $gateway->enabled() ? $gateway : null;
        }

        return $this->active();
    }
}
