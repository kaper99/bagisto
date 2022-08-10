<?php

namespace Webkul\GraphQLAPI\Mutations\Sales;

use Exception;
use Webkul\Admin\Http\Controllers\Controller;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;
use Webkul\API\Resources\Sales\Invoice;
use Webkul\Sales\Repositories\OrderRepository;
use Webkul\Sales\Repositories\InvoiceRepository;

class InvoiceMutation extends Controller
{
    /**
     * Initialize _config, a default request parameter with route
     *
     * @param array
     */
    protected $_config;

    /**
     * OrderRepository object
     *
     * @var \Webkul\Sales\Repositories\OrderRepository
     */
    protected $orderRepository;

    /**
     * InvoiceRepository object
     *
     * @var \Webkul\Sales\Repositories\InvoiceRepository
     */
    protected $invoiceRepository;

    /**
     * Create a new controller instance.
     *
     * @param  \Webkul\Sales\Repositories\OrderRepository  $orderRepository
     * @param  \Webkul\Sales\Repositories\InvoiceRepository  $invoiceRepository
     * @return void
     */
    public function __construct(
        OrderRepository $orderRepository,
        InvoiceRepository $invoiceRepository
    ) {
        $this->guard = 'admin-api';

        auth()->setDefaultDriver($this->guard);

        $this->middleware('auth:' . $this->guard);

        $this->_config = request('_config');

        $this->orderRepository = $orderRepository;

        $this->invoiceRepository = $invoiceRepository;
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
        $orderId = $params['order_id'];

        $order = $this->orderRepository->findOrFail($orderId);

        if (! $order->canInvoice()) {
            throw new Exception(trans('admin::app.sales.invoices.creation-error'));
        }

        try {

            $invoiceData= [];

            if (isset($params['invoice_data'])) {
                foreach ($params['invoice_data'] as $data) {

                    $invoiceData = $invoiceData + [
                        $data['order_item_id'] => $data['quantity']
                    ];
                }

                $invoice['invoice']['items']=  $invoiceData;

                $haveProductToInvoice = false;

                foreach ($invoice['invoice']['items'] as $itemId => $qty) {
                    if ($qty) {
                        $haveProductToInvoice = true;
                        break;
                    }
                }

                if (! $haveProductToInvoice) {
                    throw new Exception(trans('admin::app.sales.invoices.product-error'));
                }

                $invoicedData = $this->invoiceRepository->create(array_merge($invoice, ['order_id' => $orderId]));

                return $invoicedData;
            } else {
                throw new Exception(trans('admin::app.sales.invoices.product-error'));
            }
        } catch(Exception $e) {
            throw new Exception($e->getMessage());
        }
    }
}

