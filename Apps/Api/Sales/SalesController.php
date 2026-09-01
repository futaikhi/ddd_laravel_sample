<?php

declare(strict_types=1);

namespace Apps\Api\Sales;

use Apps\Api\Sales\Cancel\CancelSaleAction;
use Apps\Api\Sales\Cancel\CancelSaleRequest;
use Apps\Api\Sales\Complete\CompleteSaleAction;
use Apps\Api\Sales\Complete\CompleteSaleRequest;
use Apps\Api\Sales\Confirm\ConfirmSaleAction;
use Apps\Api\Sales\Confirm\ConfirmSaleRequest;
use Apps\Api\Sales\Create\CreateSaleAction;
use Apps\Api\Sales\Create\CreateSaleRequest;
use Apps\Api\Sales\Index\IndexSalesAction;
use Apps\Api\Sales\Index\IndexSalesRequest;
use Apps\Api\Sales\Reports\CommissionSummaryAction;
use Apps\Api\Sales\Reports\ReportDateRangeRequest;
use Apps\Api\Sales\Reports\SalesReportAction;
use Apps\Api\Sales\Show\ShowSaleAction;
use Apps\Api\Sales\Show\ShowSaleRequest;
use Illuminate\Http\JsonResponse;

final class SalesController
{
    public function create(
        CreateSaleRequest $request,
        CreateSaleAction $action,
    ): JsonResponse {
        $resource = $action($request->getDto());

        return response()->json($resource, 201);
    }

    public function confirm(
        ConfirmSaleRequest $request,
        ConfirmSaleAction $action,
    ): JsonResponse {
        $resource = $action($request->getDto());

        return response()->json($resource);
    }

    public function cancel(
        CancelSaleRequest $request,
        CancelSaleAction $action,
    ): JsonResponse {
        $resource = $action($request->getDto());

        return response()->json($resource);
    }

    public function complete(
        CompleteSaleRequest $request,
        CompleteSaleAction $action,
    ): JsonResponse {
        $resource = $action($request->getDto());

        return response()->json($resource);
    }

    public function show(
        ShowSaleRequest $request,
        ShowSaleAction $action,
    ): JsonResponse {
        $resource = $action($request->getDto());

        return response()->json($resource);
    }

    public function index(
        IndexSalesRequest $request,
        IndexSalesAction $action,
    ): JsonResponse {
        $resource = $action($request->getDto());

        return response()->json($resource);
    }

    public function salesReport(
        ReportDateRangeRequest $request,
        SalesReportAction $action,
    ): JsonResponse {
        $resource = $action($request->getDto());

        return response()->json($resource);
    }

    public function commissionSummary(
        ReportDateRangeRequest $request,
        CommissionSummaryAction $action,
    ): JsonResponse {
        $resource = $action($request->getDto());

        return response()->json($resource);
    }
}
