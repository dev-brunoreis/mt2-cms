<?php

declare(strict_types=1);

namespace Mt2Cms\Service;

use Mt2Cms\Repository\AccountRepository;
use Mt2Cms\Repository\ItemAwardRepository;
use Mt2Cms\Repository\ItemShopOrderRepository;
use Mt2Cms\Repository\ItemShopProductRepository;
use Mt2Cms\Support\Log;
use PDOException;

class ItemShopPurchaseService
{
    public function __construct(
        private ItemShopProductRepository $products,
        private ItemShopOrderRepository $orders,
        private AccountRepository $accounts,
        private ItemAwardRepository $awards,
    ) {
    }

    /**
     * @return array{order_id: int, already_completed: bool}
     */
    public function purchase(int $accountId, string $accountLogin, int $productId, string $idempotencyKey): array
    {
        $accountId = max(0, $accountId);
        $accountLogin = trim($accountLogin);
        $productId = max(0, $productId);
        $idempotencyKey = trim($idempotencyKey);

        if ($accountId < 1 || $accountLogin === '' || strlen($accountLogin) > 30) {
            throw new \InvalidArgumentException('shop.invalid_account');
        }

        if ($productId < 1) {
            throw new \InvalidArgumentException('shop.invalid_product');
        }

        if (!$this->isValidIdempotencyKey($idempotencyKey)) {
            throw new \InvalidArgumentException('shop.invalid_idempotency');
        }

        $account = $this->accounts->findById($accountId);

        if ($account === null || (string) ($account['status'] ?? '') !== 'OK') {
            throw new \RuntimeException('shop.account_blocked');
        }

        if ((string) ($account['login'] ?? '') !== $accountLogin) {
            throw new \RuntimeException('shop.invalid_account');
        }

        $product = $this->products->findEnabledForPurchase($productId);

        if ($product === null) {
            throw new \RuntimeException('shop.product_unavailable');
        }

        $price = (int) $product['price'];
        $vnum = (int) $product['vnum'];
        $count = (int) $product['count'];

        if ($price < 1 || $vnum < 1 || $count < 1 || $count > 200) {
            throw new \RuntimeException('shop.product_unavailable');
        }

        $order = $this->resolveOrder(
            $accountId,
            $accountLogin,
            $product,
            $idempotencyKey,
        );

        if ((int) $order['product_id'] !== $productId || (int) $order['account_id'] !== $accountId) {
            throw new \RuntimeException('shop.invalid_idempotency');
        }

        if ((string) $order['status'] === 'completed') {
            return [
                'order_id' => (int) $order['id'],
                'already_completed' => true,
            ];
        }

        if ((string) $order['status'] === 'failed') {
            throw new \RuntimeException('shop.purchase_failed');
        }

        $lockName = 'itemshop:' . $accountId;

        if (!$this->accounts->acquireNamedLock($lockName, 5)) {
            throw new \RuntimeException('shop.busy');
        }

        try {
            $order = $this->orders->findById((int) $order['id']);

            if ($order === null) {
                throw new \RuntimeException('shop.purchase_failed');
            }

            if ((string) $order['status'] === 'completed') {
                return [
                    'order_id' => (int) $order['id'],
                    'already_completed' => true,
                ];
            }

            if ((string) $order['status'] === 'failed') {
                throw new \RuntimeException('shop.purchase_failed');
            }

            $orderId = (int) $order['id'];
            $why = 'shop:' . $orderId;
            $awardId = (int) ($order['item_award_id'] ?? 0);
            $price = (int) $order['price'];
            $vnum = (int) $order['vnum'];
            $count = (int) $order['count'];
            $socket0 = (int) $order['socket0'];
            $socket1 = (int) $order['socket1'];
            $socket2 = (int) $order['socket2'];

            if ($price < 1 || $vnum < 1 || $count < 1 || $count > 200) {
                $this->safeMarkFailed($orderId);

                throw new \RuntimeException('shop.product_unavailable');
            }

            if ($awardId < 1) {
                $existingAwardId = $this->awards->findPendingIdByWhyPrefix($why);

                if ($existingAwardId !== null) {
                    $awardId = $existingAwardId;
                    $this->safeAttachAward($orderId, $awardId);
                } else {
                    $awardId = $this->awards->createMallAward([
                        'login' => $accountLogin,
                        'vnum' => $vnum,
                        'count' => $count,
                        'socket0' => $socket0,
                        'socket1' => $socket1,
                        'socket2' => $socket2,
                        'why' => $why,
                    ]);
                    $this->safeAttachAward($orderId, $awardId);
                }
            }

            $cashDebited = (int) ($order['cash_debited'] ?? 0) === 1
                || $this->awards->isShopAwardPaid($awardId, $why);

            if (!$cashDebited) {
                if (!$this->accounts->debitCash($accountId, $price)) {
                    $this->awards->deletePending($awardId);
                    $this->safeMarkFailed($orderId);

                    throw new \RuntimeException('shop.insufficient_cash');
                }

                // Persist debit proof on the game DB first (MyISAM), then CMS.
                $this->awards->markShopAwardPaid($awardId, $why);
                $this->safeMarkCashDebited($orderId);
            }

            $this->safeMarkCompleted($orderId, $awardId);

            return [
                'order_id' => $orderId,
                'already_completed' => false,
            ];
        } finally {
            $this->accounts->releaseNamedLock($lockName);
        }
    }

    /**
     * @param array<string, mixed> $product
     * @return array<string, mixed>
     */
    private function resolveOrder(
        int $accountId,
        string $accountLogin,
        array $product,
        string $idempotencyKey,
    ): array {
        $existing = $this->orders->findByIdempotencyKey($idempotencyKey);

        if ($existing !== null) {
            if ((int) $existing['account_id'] !== $accountId
                || (int) $existing['product_id'] !== (int) $product['id']) {
                throw new \RuntimeException('shop.invalid_idempotency');
            }

            return $existing;
        }

        try {
            return $this->orders->createPending([
                'account_id' => $accountId,
                'account_login' => $accountLogin,
                'product_id' => (int) $product['id'],
                'vnum' => (int) $product['vnum'],
                'count' => (int) $product['count'],
                'price' => (int) $product['price'],
                'socket0' => (int) $product['socket0'],
                'socket1' => (int) $product['socket1'],
                'socket2' => (int) $product['socket2'],
                'idempotency_key' => $idempotencyKey,
            ]);
        } catch (PDOException $e) {
            if (!$this->isDuplicateKey($e)) {
                throw $e;
            }

            $existing = $this->orders->findByIdempotencyKey($idempotencyKey);

            if ($existing === null) {
                throw $e;
            }

            if ((int) $existing['account_id'] !== $accountId
                || (int) $existing['product_id'] !== (int) $product['id']) {
                throw new \RuntimeException('shop.invalid_idempotency');
            }

            return $existing;
        }
    }

    private function safeAttachAward(int $orderId, int $awardId): void
    {
        try {
            $this->orders->attachAward($orderId, $awardId);
        } catch (\Throwable $e) {
            Log::error('item_shop', 'Failed to attach award id to order', $e);
        }
    }

    private function safeMarkCashDebited(int $orderId): void
    {
        try {
            $this->orders->markCashDebited($orderId);
        } catch (\Throwable $e) {
            Log::error('item_shop', 'Failed to mark cash_debited after successful debit', $e);
        }
    }

    private function safeMarkCompleted(int $orderId, int $awardId): void
    {
        try {
            $this->orders->markCompleted($orderId, $awardId);
        } catch (\Throwable $e) {
            Log::error('item_shop', 'Failed to mark order completed after successful purchase', $e);
        }
    }

    private function safeMarkFailed(int $orderId): void
    {
        try {
            $this->orders->markFailed($orderId);
        } catch (\Throwable $e) {
            Log::error('item_shop', 'Failed to mark order failed after debit rejection', $e);
        }
    }

    private function isValidIdempotencyKey(string $key): bool
    {
        return (bool) preg_match('/^[a-f0-9]{32,64}$/', $key);
    }

    private function isDuplicateKey(PDOException $e): bool
    {
        $code = $e->errorInfo[1] ?? null;

        return (int) $code === 1062 || str_contains($e->getMessage(), 'Duplicate');
    }
}
