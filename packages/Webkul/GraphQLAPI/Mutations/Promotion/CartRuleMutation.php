<?php

namespace Webkul\GraphQLAPI\Mutations\Promotion;

use Exception;
use Webkul\Admin\Http\Controllers\Controller;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;
use Webkul\CartRule\Repositories\CartRuleRepository;
use Webkul\CartRule\Repositories\CartRuleCouponRepository;
use Illuminate\Support\Facades\Event;

class CartRuleMutation extends Controller
{
    /**
     * Initialize _config, a default request parameter with route
     *
     * @param array
     */
    protected $_config;

    /**
     * To hold Cart repository instance
     *
     * @var \Webkul\CartRule\Repositories\CartRuleRepository
     */
    protected $cartRuleRepository;

    /**
     * To hold CartRuleCouponRepository repository instance
     *
     * @var \Webkul\CartRule\Repositories\CartRuleCouponRepository
     */
    protected $cartRuleCouponRepository;

    /**
     * Create a new controller instance.
     *
     * @param \Webkul\CartRule\Repositories\CartRuleRepository       $cartRuleRepository
     * @param \Webkul\CartRule\Repositories\CartRuleCouponRepository $cartRuleCouponRepository
     * @return void
     */
    public function __construct(
        CartRuleRepository $cartRuleRepository,
        CartRuleCouponRepository $cartRuleCouponRepository
    ) {
        $this->guard = 'admin-api';

        auth()->setDefaultDriver($this->guard);

        $this->middleware('auth:' . $this->guard);

        $this->_config = request('_config');

        $this->cartRuleRepository = $cartRuleRepository;

        $this->cartRuleCouponRepository = $cartRuleCouponRepository;
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function store($rootValue, array $args, GraphQLContext $context)
    {
        if (! isset($args['input']) || (isset($args['input']) && !$args['input'])) {
            throw new Exception(trans('bagisto_graphql::app.admin.response.error-invalid-parameter'));
        }

        if (! bagisto_graphql()->validateAPIUser($this->guard)) {
            throw new Exception(trans('bagisto_graphql::app.admin.response.invalid-header'));
        }

        $params = $args['input'];

        if ( $params['use_auto_generation'] == true) {
            $params['use_auto_generation'] = 1;
        } else {
            $params['use_auto_generation'] = 0;
        }

        $validator = \Validator::make($params, [
            'name'                => 'required',
            'channels'            => 'required|array|min:1',
            'customer_groups'     => 'required|array|min:1',
            'coupon_type'         => 'required',
            'use_auto_generation' => 'required_if:coupon_type,==,1',
            'coupon_code'         => 'required_if:use_auto_generation,==,0',
            'starts_from'         => 'nullable|date',
            'ends_till'           => 'nullable|date|after_or_equal:starts_from',
            'action_type'         => 'required',
            'discount_amount'     => 'required|numeric'
        ]);

        if ($validator->fails()) {
            throw new Exception($validator->messages());
        }

        try {
            Event::dispatch('promotions.cart_rule.create.before');

            $cartRule = $this->cartRuleRepository->create($params);

            Event::dispatch('promotions.cart_rule.create.after', $cartRule);

            return $cartRule;
        } catch(\Exception $e) {
            throw new Exception($e->getMessage());
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update($rootValue, array $args, GraphQLContext $context)
    {
        if (! isset($args['id']) || !isset($args['input']) || (isset($args['input']) && !$args['input'])) {
            throw new Exception(trans('bagisto_graphql::app.admin.response.error-invalid-parameter'));
        }

        if (! bagisto_graphql()->validateAPIUser($this->guard)) {
            throw new Exception(trans('bagisto_graphql::app.admin.response.invalid-header'));
        }

        $params = $args['input'];
        $id = $args['id'];

        if ( $params['use_auto_generation'] == true) {
            $params['use_auto_generation'] = 1;
        } else {
            $params['use_auto_generation'] = 0;
        }

        $validator = \Validator::make($params, [
            'name'                => 'required',
            'channels'            => 'required|array|min:1',
            'customer_groups'     => 'required|array|min:1',
            'coupon_type'         => 'required',
            'use_auto_generation' => 'required_if:coupon_type,==,1',
            'coupon_code'         => 'required_if:use_auto_generation,==,0',
            'starts_from'         => 'nullable|date',
            'ends_till'           => 'nullable|date|after_or_equal:starts_from',
            'action_type'         => 'required',
            'discount_amount'     => 'required|numeric'
        ]);

        if ($validator->fails()) {
            throw new Exception($validator->messages());
        }

        try {

            $cartRule = $this->cartRuleRepository->findOrFail($id);

            Event::dispatch('promotions.cart_rule.update.before', $cartRule);

            if (isset($params['autogenerated_coupons'])) {

                $coupons = $this->generateCoupons($params['autogenerated_coupons'], $id);

                unset($params['autogenerated_coupons']);
            }

            $cartRule = $this->cartRuleRepository->update($params, $id);

            Event::dispatch('promotions.cart_rule.update.after', $cartRule);

            return $cartRule;
        } catch(\Exception $e) {
            throw new Exception($e->getMessage());
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function delete($rootValue, array $args, GraphQLContext $context)
    {
        if (! isset($args['id']) || (isset($args['id']) && !$args['id'])) {
            throw new Exception(trans('bagisto_graphql::app.admin.response.error-invalid-parameter'));
        }

        if (! bagisto_graphql()->validateAPIUser($this->guard)) {
            throw new Exception(trans('bagisto_graphql::app.admin.response.invalid-header'));
        }

        $id = $args['id'];

        $cartRule = $this->cartRuleRepository->find($id);

        try {

            if ($cartRule != Null) {

                Event::dispatch('promotions.cart_rule.delete.before', $id);

                $cartRule->delete();

                Event::dispatch('promotions.cart_rule.delete.after', $id);

                return ['success' => trans('admin::app.response.delete-success', ['name' => 'Cart Rule'])];
            } else {
                throw new Exception(trans('admin::app.response.delete-failed', ['name' => 'Cart Rule']));
            }
        } catch (Exception $e) {

            throw new Exception($e->getMessage());
        }
    }

    /**
     * Generate coupon code for cart rule
     *
     * @return Response
     */
    public function generateCoupons($params, $id)
    {
        $validator = \Validator::make($params, [
            'coupon_qty'  => 'required|integer|min:1',
            'code_length' => 'required|integer|min:10',
            'code_format' => 'required',
        ]);

        try {

            if (! $id) {
                throw new Exception(trans('admin::app.promotions.cart-rules.cart-rule-not-defind-error'));
            }

            $coupon = $this->cartRuleCouponRepository->generateCoupons($params, $id);

            return $coupon;
        } catch(Exception $e) {
            throw new Exception($e->getMessage());
        }

    }
}

