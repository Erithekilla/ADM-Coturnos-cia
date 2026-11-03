<?php

/**
 * Cobertura de IDs cifrados (diretriz 3.2).
 *
 * Não depende de banco: confere a serialização dos models e faz uma varredura
 * estática nos controllers para impedir que alguém volte a buscar registros
 * pelo inteiro cru da URL (`find($id)`), o que reabriria a enumeração.
 */

use App\Models\AuditLog;
use App\Models\Banner;
use App\Models\BannerImage;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Collection;
use App\Models\CollectionImage;
use App\Models\CollectionProduct;
use App\Models\Order;
use App\Models\OrderHistory;
use App\Models\OrderItem;
use App\Models\PackedProduct;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductPrice;
use App\Models\ProductPromotion;
use App\Models\ProductSize;
use App\Models\User;

function comAtributos(string $model, array $attributes)
{
    return (new $model)->setRawAttributes($attributes, true);
}

dataset('models_com_id_cifrado', [
    User::class, Product::class, Order::class, Payment::class, Cart::class, OrderItem::class,
    OrderHistory::class, ProductSize::class, ProductImage::class, ProductPrice::class,
    ProductPromotion::class, Collection::class, CollectionImage::class, Banner::class,
    BannerImage::class, PackedProduct::class, AuditLog::class,
]);

test('a chave primária sai cifrada e volta para o inteiro', function (string $model) {
    $json = comAtributos($model, ['id' => 42])->toArray();

    expect($json['id'])->toBeString()->toHaveLength(22)
        ->and($model::decodeHashid($json['id']))->toBe(42)
        ->and($model::decodeHashid('42'))->toBeNull(); // inteiro cru é rejeitado
})->with('models_com_id_cifrado');

test('ID público de uma tabela não é aceito em outra', function () {
    $idBanner = Banner::encodeId(7);

    expect(BannerImage::decodeHashid($idBanner))->toBeNull()
        ->and(Collection::decodeHashid($idBanner))->toBeNull();
});

test('chaves estrangeiras saem com o ID público do pai', function () {
    expect(comAtributos(BannerImage::class, ['id' => 1, 'banner_id' => 3])->toArray()['banner_id'])->toBe(Banner::encodeId(3))
        ->and(comAtributos(CollectionImage::class, ['id' => 1, 'collection_id' => 3])->toArray()['collection_id'])->toBe(Collection::encodeId(3))
        ->and(comAtributos(ProductPromotion::class, ['id' => 1, 'price_id' => 3])->toArray()['price_id'])->toBe(ProductPrice::encodeId(3))
        ->and(comAtributos(Payment::class, ['id' => 1, 'order_id' => 3])->toArray()['order_id'])->toBe(Order::encodeId(3))
        ->and(comAtributos(CartItem::class, ['cart_id' => 3, 'product_id' => 4])->toArray()['cart_id'])->toBe(Cart::encodeId(3))
        ->and(comAtributos(CollectionProduct::class, ['collection_id' => 3, 'product_id' => 4])->toArray()['collection_id'])->toBe(Collection::encodeId(3));
});

test('auditoria expõe autor e registro afetado cifrados', function () {
    $json = comAtributos(AuditLog::class, [
        'id' => 9, 'admin_user_id' => 2, 'table_affected' => 'products', 'record_id' => 5,
    ])->toArray();

    expect($json['admin_user_id'])->toBe(User::encodeId(2))
        ->and($json['record_id'])->toBe(Product::encodeId(5));
});

test('nenhum controller busca registro pelo ID cru da rota', function () {
    $offenders = [];

    foreach (glob(app_path('Http/Controllers/*.php')) as $file) {
        foreach (file($file) as $n => $line) {
            if (preg_match('/->find\(\$id\)|::find\(\$id\)|findOrFail\(\$id\)/', $line)) {
                $offenders[] = basename($file) . ':' . ($n + 1) . ' ' . trim($line);
            }
        }
    }

    expect($offenders)->toBe([]);
});
