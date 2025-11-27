<?php

namespace App\Imports;

use App\Models\Bundle;
use App\Models\Product;
use App\Models\ProductOrder;
use App\Models\Sale;
use App\Models\Webinar;
use App\User;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Illuminate\Support\Facades\Log;

class WebinarSaleImport implements ToCollection, WithHeadingRow
{
    public function __construct(protected $data, protected $rules)
    {
    }

    public function collection(Collection $collection)
    {
        // Validate the item type (webinar, bundle, or product)
        $this->validateItemType();

        $results = [
            'success_count' => 0,
            'error_count' => 0,
            'errors' => []
        ];

        foreach ($collection as $row) {
            $this->processRow($row, $results);
        }

        $this->logResults($results);
        return $results;
    }

    protected function validateItemType(): void
    {
        if (!empty($this->data['webinar_id'])) {
            $this->rules['webinar_id'] = 'required|exists:webinars,id';
            $this->itemType = 'webinar';
        } elseif (!empty($this->data['bundle_id'])) {
            $this->rules['bundle_id'] = 'required|exists:bundles,id';
            $this->itemType = 'bundle';
        } elseif (!empty($this->data['product_id'])) {
            $this->rules['product_id'] = 'required|exists:products,id';
            $this->itemType = 'product';
        } else {
            throw new \Exception('No valid item type provided (webinar_id, bundle_id, or product_id)');
        }
    }

    protected function processRow($row, array &$results): void
    {
        try {
            if (!$row->get('email')) {
                $results['error_count']++;
                return;
            }

            $user = User::where('email', $row->get('email'))->first();
            if (!$user) {
                $results['error_count']++;
                $results['errors'][] = "User with email {$row->get('email')} not found";
                return;
            }

            $item = $this->getItem();
            if (!$item) {
                $results['error_count']++;
                $results['errors'][] = "Item not found for user {$user->email}";
                return;
            }

            $validation = $this->validatePurchase($user, $item);
            if (!$validation['valid']) {
                $results['error_count']++;
                $results['errors'][] = $validation['message'];
                return;
            }

            $this->createSale($user, $item, $results);

        } catch (\Exception $e) {
            $results['error_count']++;
            $results['errors'][] = "Error processing row: " . $e->getMessage();
            Log::error("Error in WebinarSaleImport: " . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'row' => $row
            ]);
        }
    }

    protected function getItem()
    {
        switch ($this->itemType) {
            case 'webinar':
                return Webinar::find($this->data['webinar_id']);
            case 'bundle':
                return Bundle::find($this->data['bundle_id']);
            case 'product':
                return Product::find($this->data['product_id']);
            default:
                return null;
        }
    }

    protected function validatePurchase(User $user, $item): array
    {
        $isOwner = false;
        $hasBought = false;
        $errorMessage = '';

        switch ($this->itemType) {
            case 'webinar':
                $isOwner = $item->isOwner($user->id);
                $hasBought = $item->checkUserHasBought($user);
                $errorMessage = $isOwner
                    ? trans('cart.cant_purchase_your_course')
                    : trans('site.you_bought_webinar');
                break;
            case 'bundle':
                $isOwner = $item->isOwner($user->id);
                $hasBought = $item->checkUserHasBought($user);
                $errorMessage = $isOwner
                    ? trans('cart.cant_purchase_your_course')
                    : trans('update.you_bought_bundle');
                break;
            case 'product':
                $isOwner = ($item->creator_id == $user->id);
                $hasBought = $item->checkUserHasBought($user);
                $errorMessage = $isOwner
                    ? trans('update.cant_purchase_your_product')
                    : trans('update.you_bought_product');
                break;
        }

        return [
            'valid' => !$isOwner && !$hasBought,
            'message' => $errorMessage
        ];
    }

    protected function createSale(User $user, $item, array &$results): void
    {
        $productOrder = null;
        $itemColumnName = $this->getItemColumnName();

        if ($this->itemType === 'product') {
            $productOrder = ProductOrder::create([
                'product_id' => $item->id,
                'seller_id' => $item->creator_id,
                'buyer_id' => $user->id,
                'specifications' => null,
                'quantity' => 1,
                'status' => 'pending',
                'created_at' => time()
            ]);
        }

        $saleData = [
            'buyer_id' => $user->id,
            'seller_id' => $item->creator_id,
            $itemColumnName => $this->itemType === 'product' ? $productOrder->id : $item->id,
            'online_payment_method' => $this->getOnlinePaymentMethod(),
            'online_payment_method_id' => $this->getOnlinePaymentMethodId(),
            'type' => $this->getSaleType(),
            'manual_added' => true,
            'payment_method' => Sale::$credit,
            'amount' => $this->data['paid_amount'] ?? 0,
            'total_amount' => $this->data['paid_amount'] ?? 0,
            'created_at' => time()
        ];

        $sale = Sale::create($saleData);

        if ($this->itemType === 'product' && $productOrder) {
            $productOrder->update([
                'sale_id' => $sale->id,
                'status' => $item->isVirtual() ? ProductOrder::$success : ProductOrder::$waitingDelivery,
            ]);
        }

        $results['success_count']++;
    }

    protected function getItemColumnName(): string
    {
        return match($this->itemType) {
            'webinar' => 'webinar_id',
            'bundle' => 'bundle_id',
            'product' => 'product_order_id',
            default => throw new \Exception('Invalid item type')
        };
    }

    protected function getSaleType(): string
    {
        return match($this->itemType) {
            'webinar' => Sale::$webinar,
            'bundle' => Sale::$bundle,
            'product' => Sale::$product,
            default => throw new \Exception('Invalid item type')
        };
    }

    protected function getOnlinePaymentMethod(): string
    {
        return isset($this->data['online_payment_method']) &&
        $this->data['online_payment_method'] &&
        isset($this->data['paid_amount']) &&
        $this->data['paid_amount']
            ? $this->data['online_payment_method']
            : 'تحويل مباشر';
    }

    protected function getOnlinePaymentMethodId(): ?string
    {
        return isset($this->data['online_payment_method_id']) &&
        $this->data['online_payment_method_id'] &&
        isset($this->data['paid_amount']) &&
        $this->data['paid_amount']
            ? $this->data['online_payment_method_id']
            : null;
    }

    protected function logResults(array $results): void
    {
        Log::info("WebinarSaleImport completed", [
            'success_count' => $results['success_count'],
            'error_count' => $results['error_count'],
            'item_type' => $this->itemType,
            'item_id' => $this->data[$this->itemType.'_id'] ?? null
        ]);
    }
}
