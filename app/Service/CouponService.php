<?php
/**
 * The file was created by Assimon.
 *
 * @author    assimon<ashang@utf8.hk>
 * @copyright assimon<ashang@utf8.hk>
 * @link      http://utf8.hk/
 */

namespace App\Service;


use App\Models\Coupon;

class CouponService
{

    /**
     * 获得优惠码，通过商品关联
     *
     * @param string $coupon 优惠码
     * @param int $goodsID 商品id
     * @return \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Model|object|null
     *
     * @author    assimon<ashang@utf8.hk>
     * @copyright assimon<ashang@utf8.hk>
     * @link      http://utf8.hk/
     */
    public function withHasGoods(string $coupon, int $goodsID)
    {
        $coupon = Coupon::query()->whereHas('goods', function ($query) use ($goodsID) {
            $query->where('goods_id', $goodsID);
        })->where('is_open', Coupon::STATUS_OPEN)
          ->where('ret', '>', 0)
          ->where('coupon', $coupon)->first();
        return $coupon;
    }

    /**
     * 设置优惠券已使用
     * @param string $coupon
     * @return bool
     */
    public function used(string $coupon): bool
    {
        // 仅在最后一次使用时（ret<=1）标记为已使用，支持多次使用优惠券
        return Coupon::query()
            ->where('coupon', $coupon)
            ->where('ret', '<=', 1)
            ->update(['is_use' => Coupon::STATUS_USE]) > 0;
    }

    /**
     * 设置优惠券使用次数 -1
     * @param string $coupon
     * @return int
     *
     * @author    assimon<ashang@utf8.hk>
     * @copyright assimon<ashang@utf8.hk>
     * @link      http://utf8.hk/
     */
    public function retDecr(string $coupon)
    {
        // 原子操作：仅在剩余次数大于0时递减，防止并发导致负数
        return Coupon::query()
            ->where('coupon', $coupon)
            ->where('ret', '>', 0)
            ->decrement('ret', 1);
    }

    /**
     * 设置优惠券次数+1
     *
     * @param int $id
     * @return int
     *
     * @author    assimon<ashang@utf8.hk>
     * @copyright assimon<ashang@utf8.hk>
     * @link      http://utf8.hk/
     */
    public function retIncrByID(int $id)
    {
        return Coupon::query()->where('id',  $id)->increment('ret', 1);
    }

}
