<?php

namespace webdophp\WebkassaIntegration\Http\Resources\v1;

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
                'id' => $item->id,
                'operation_type' => $item->operation_type,
                'operation_type_text' => $item->operation_type_text,
                'sum' => $item->total,
                'discount' => $item->discount,
                'markup' => $item->markup,
                'date_operation' => $item->date,
                'number' => $item->number,
                'unique_id' => $item->order_number,
                'shift_number' => $item->shift->shift_number ?? null,
                'open_date' => $item->shift->open_date ?? null,
                'close_date' => $item->shift->close_date ?? null,
                'cashbox_unique_number' => $item->shift->cashbox->cashbox_unique_number ?? null,
                'tax_payer_bin' => $item->shift->cashbox->xin ?? null,
                'organization_name' => $item->shift->cashbox->organization_name ?? null,
                'positions' => $item->positions ?? null,
                'payments' => $item->payments ?? null,
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
