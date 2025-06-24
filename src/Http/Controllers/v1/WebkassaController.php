<?php

namespace webdophp\WebkassaIntegration\Http\Controllers\v1;


use Exception;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use webdophp\WebkassaIntegration\Http\Resources\v1\WebkassaCollection;
use webdophp\WebkassaIntegration\Models\ControlTapeRecord;


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
     */
    public function data(): JsonResponse|WebkassaCollection
    {
        try{
            $records = ControlTapeRecord::where('received_data', false)
                ->orderBy('id', 'ASC')
                ->limit(100)
                ->get();
            //Если нету строк, то статус код 204 нет данных
            if ($records->isEmpty()) {
                return response()->json()->setStatusCode(Response::HTTP_NO_CONTENT);
            }
            //Берем только id
            $ids = $records->pluck('id');
            //Обновляем данные и говорим, что показали данные в запросе
            ControlTapeRecord::whereIn('id', $ids)->update([
                'sent_data' => true,
                'date_sent_data' => now(),
            ]);

            return new WebkassaCollection($records);

        } catch (Exception $e) {
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
            ControlTapeRecord::where('sent_data', true)->update(['received_data' => true]);
            return response()->json(['status' => 'success', 'message' => Response::$statusTexts[Response::HTTP_OK]]);

        } catch (Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }
}
