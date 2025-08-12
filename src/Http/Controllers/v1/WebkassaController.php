<?php

namespace webdophp\WebkassaIntegration\Http\Controllers\v1;


use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;
use webdophp\WebkassaIntegration\Http\Resources\v1\WebkassaCollection;
use webdophp\WebkassaIntegration\Models\Ticket;


class WebkassaController
{
    /**
     * Проверка работает сервис или нет
     * @return JsonResponse
     */
    public function ping(): JsonResponse
    {
        return response()->json(['status' => 'success', 'message' => 'Webkassa API Controller is working!']);
    }


    /**
     * Взять пачку записей
     * @return JsonResponse|WebkassaCollection
     * @throws \Throwable
     */
    public function data(): JsonResponse|WebkassaCollection
    {

        DB::beginTransaction();
        try{
            $records = Ticket::where('received_data', false)
                ->select( 'id', 'shift_id', 'number', 'order_number',
                    'date', 'operation_type', 'operation_type_text',
                    'total', 'discount', 'markup', 'sent_data', 'date_sent_data', 'received_data')
                ->with([
                    'shift' => function ($query) {
                        $query->select('id', 'cashbox_id', 'shift_number', 'open_date', 'close_date')
                            ->with(['cashbox' => function ($query) {
                                $query->select('id', 'cashbox_unique_number', 'xin', 'organization_name');
                            }]);
                    },
                    'payments' => function ($query) {
                        $query->select('id','ticket_id','sum','payment_type','payment_type_name');
                    },
                    'positions' => function ($query) {
                        $query->select('id','ticket_id','position_name','count','price', 'discount_tenge', 'markup', 'sum');
                    }
                ])
                ->orderBy('id', 'ASC')
                ->limit(100)
                ->lockForUpdate() // блокировка до конца транзакции (другие параллельные вызовы будут ждать)
                ->get();

            //Если нет строк, то статус код 204 нет данных
            if ($records->isEmpty()) {
                return response()->json()->setStatusCode(Response::HTTP_NO_CONTENT);
            }
            //Берем только id
            $ids = $records->pluck('id');
            //Обновляем данные и говорим, что показали данные в запросе
            Ticket::whereIn('id', $ids)->update([
                'sent_data' => true,
                'date_sent_data' => now(),
            ]);

            DB::commit();
            return new WebkassaCollection($records);

        } catch (Exception $e) {
            DB::rollBack();
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Подтвердить получение данных
     * @return JsonResponse
     */
    public function confirm(): JsonResponse
    {
        try{
            //Обновляем данные и говорим, что мы показали и приняли данные и больше их не показываем
            Ticket::where('sent_data', true)->update(['received_data' => true]);
            return response()->json(['status' => 'success', 'message' => Response::$statusTexts[Response::HTTP_OK]]);

        } catch (Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }
}
