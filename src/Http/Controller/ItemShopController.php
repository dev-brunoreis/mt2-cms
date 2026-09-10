<?php

declare(strict_types=1);

namespace Mt2Cms\Http\Controller;

use Mt2Cms\Auth\Auth;
use Mt2Cms\Auth\Csrf;
use Mt2Cms\Auth\RateLimiter;
use Mt2Cms\Http\Response;
use Mt2Cms\I18n\Translator;
use Mt2Cms\Repository\ItemShopCategoryRepository;
use Mt2Cms\Repository\ItemShopProductRepository;
use Mt2Cms\Service\ItemShopPurchaseService;
use Mt2Cms\Service\ItemTooltipBuilder;
use Mt2Cms\Theme\ThemeEngine;

class ItemShopController extends Controller
{
    private const SESSION_IDEMPOTENCY = '_item_shop_idempotency';

    private RateLimiter $rateLimiter;

    public function __construct(
        ThemeEngine $theme,
        Auth $auth,
        Csrf $csrf,
        Translator $translator,
        private ItemShopCategoryRepository $categories,
        private ItemShopProductRepository $products,
        private ItemShopPurchaseService $purchases,
        private ItemTooltipBuilder $tooltips,
    ) {
        parent::__construct($theme, $auth, $csrf, $translator);
        $this->rateLimiter = new RateLimiter(10, 60);
    }

    public function index(): Response
    {
        if ($redirect = $this->requireAuth()) {
            return $redirect;
        }

        $slug = trim((string) ($_GET['category'] ?? ''));
        $categories = $this->categories->treeEnabled();
        $activeCategory = null;
        $categoryIds = null;
        $expandedCategoryIds = [];

        if ($slug !== '') {
            $activeCategory = $this->categories->findEnabledBySlug($slug);

            if ($activeCategory === null) {
                return $this->view('shop', [
                    'title' => $this->t('shop.not_found'),
                    'notFound' => true,
                    'categories' => $categories,
                    'products' => [],
                    'activeCategory' => null,
                    'expandedCategoryIds' => [],
                    'cash' => $this->currentCash(),
                    'idempotencyKeys' => [],
                ], 404);
            }

            $categoryIds = [(int) $activeCategory['id']];
            $expandedCategoryIds = $this->categories->ancestorIds((int) $activeCategory['id']);
        }

        $products = $this->enrichProducts($this->products->listEnabled($categoryIds));
        $idempotencyKeys = [];

        if ($this->auth->check()) {
            foreach ($products as $product) {
                $idempotencyKeys[(int) $product['id']] = $this->ensureIdempotencyKey((int) $product['id']);
            }
        }

        return $this->view('shop', [
            'title' => $this->t('shop.title'),
            'notFound' => false,
            'categories' => $categories,
            'products' => $products,
            'activeCategory' => $activeCategory,
            'expandedCategoryIds' => $expandedCategoryIds,
            'cash' => $this->currentCash(),
            'idempotencyKeys' => $idempotencyKeys,
        ]);
    }

    public function buy(): Response
    {
        if ($redirect = $this->requireAuth()) {
            return $redirect;
        }

        if (!$this->assertCsrf()) {
            $this->flash('error', $this->t('auth.invalid_csrf'));

            return $this->redirect('/shop');
        }

        $accountId = $this->auth->id();
        $accountLogin = $this->auth->login();

        if ($accountId === null || $accountLogin === null || $accountLogin === '') {
            $this->flash('error', $this->t('auth.login_required'));

            return $this->redirect('/login');
        }

        $bucket = 'item_shop_buy:' . $accountId;

        if ($this->rateLimiter->tooManyAttempts($bucket)) {
            $this->flash('error', $this->t('shop.rate_limited'));

            return $this->redirect('/shop');
        }

        $this->rateLimiter->hit($bucket);

        $productId = (int) ($_POST['product_id'] ?? 0);
        $idempotencyKey = trim((string) ($_POST['idempotency_key'] ?? ''));
        $sessionKeys = $_SESSION[self::SESSION_IDEMPOTENCY] ?? [];

        if (!is_array($sessionKeys)) {
            $sessionKeys = [];
        }

        $expected = (string) ($sessionKeys[$productId] ?? '');

        if ($productId < 1 || $idempotencyKey === '' || $expected === '' || !hash_equals($expected, $idempotencyKey)) {
            $this->flash('error', $this->t('shop.invalid_idempotency'));

            return $this->redirect('/shop');
        }

        try {
            $result = $this->purchases->purchase($accountId, $accountLogin, $productId, $idempotencyKey);
            unset($sessionKeys[$productId]);
            $_SESSION[self::SESSION_IDEMPOTENCY] = $sessionKeys;

            $this->flash(
                'success',
                $this->t($result['already_completed'] ? 'shop.already_purchased' : 'shop.purchased'),
            );
        } catch (\InvalidArgumentException | \RuntimeException $e) {
            $this->flash('error', $this->t($e->getMessage()));
        }

        return $this->redirect('/shop');
    }

    private function ensureIdempotencyKey(int $productId): string
    {
        $all = $_SESSION[self::SESSION_IDEMPOTENCY] ?? [];

        if (!is_array($all)) {
            $all = [];
        }

        $existing = (string) ($all[$productId] ?? '');

        if (preg_match('/^[a-f0-9]{32,64}$/', $existing)) {
            return $existing;
        }

        $key = bin2hex(random_bytes(16));
        $all[$productId] = $key;
        $_SESSION[self::SESSION_IDEMPOTENCY] = $all;

        return $key;
    }

    private function currentCash(): ?int
    {
        if (!$this->auth->check()) {
            return null;
        }

        $user = $this->auth->user();

        if ($user === null) {
            return null;
        }

        return (int) ($user['cash'] ?? 0);
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    private function enrichProducts(array $rows): array
    {
        foreach ($rows as &$row) {
            $vnum = (int) $row['vnum'];
            $tip = $this->tooltips->forVnum($vnum, [
                'socket0' => (int) ($row['socket0'] ?? 0),
                'socket1' => (int) ($row['socket1'] ?? 0),
                'socket2' => (int) ($row['socket2'] ?? 0),
            ]);
            $row['item_name'] = $tip['name'] !== '' ? $tip['name'] : (string) $vnum;
            $row['tooltip'] = $tip;
        }

        unset($row);

        return $rows;
    }
}
