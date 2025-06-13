<?php

namespace webdophp\WebkassaIntegration\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\Response;

class WebkassaCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into a Collection.
     *
     * @param Request $request
     * @return Collection
     */
    public function toArray(Request $request): Collection
    {
        return $this->collection->transform(function ($item) use ($request) {
            return  [
                'cashbox_unique_number' => $item->cashbox_unique_number,
                'shift_number' => $item->shift_number,
                'operation_type' => $item->operation_type,
                'sum' => $item->sum,
                'date_operation' => $item->date,
                'employee_code' => $item->employee_code,
                'number' => $item->number,
                'is_offline' => $item->is_offline,
            ];
        });
    }

    /**
     * @param $request
     * @return array
     */
    public function with($request): array
    {
        return [
            'status' => 'success',
            'message' => Response::$statusTexts[Response::HTTP_OK],
        ];
    }
}
