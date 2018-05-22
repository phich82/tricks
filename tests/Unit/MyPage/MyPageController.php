<?php

namespace App\Http\Controllers\Traveler;

use Exception;
use App\Api\BookingApi;
use App\Models\Booking;
use App\Api\ActivityApi;
use App\Models\BookingLog;
use App\Commons\SessionFake;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Cookie;
use App\Repositories\BookingRepository;
use App\Repositories\BookingLogRepository;
use App\Http\Requests\MyPage\BookingUpdate;
use Illuminate\Database\Eloquent\Collection;

class MyPageController extends Controller
{
    /**
     * @var bookingRepository
     */
    protected $bookingRepository;

    /**
     * @var bookingLogRepository
     */
    protected $bookingLogRepository;

    /**
     * @var BookingApi
     */
    protected $api;

    /**
     * @var ActivityApi
     */
    protected $activityApi;

    /**
     * constructor
     *
     * @param BookingRepository $bookingRepository
     * @param BookingLogRepository $bookingLogRepository
     * @param BookingApi $api
     */
    public function __construct(BookingRepository $bookingRepository, BookingLogRepository $bookingLogRepository, BookingApi $api, ActivityApi $activityApi)
    {
        $this->bookingRepository = $bookingRepository;
        $this->bookingLogRepository = $bookingLogRepository;
        $this->api = $api;
        $this->activityApi = $activityApi;

        $this->enumsBookingStatus = config('enums')['booking_status'];
        $this->BookingStatusJP    = config('forms.BookingManager')['booking_status_jp'];

        $configPrivate = config('forms.BookingManager');
        view()->share(['formData' => $configPrivate]);
    }

    /**
     * MyPage - List Screen
     *
     * @param  Request $request
     * @return \Illuminate\Http\Response
    */
    public function index(Request $request)
    {
        $user = SessionFake::get('amc_info');
        if ($user) {
            $user['datetime'] = date('Y年m月d日 H:i現在');
            $statuses = [
                \Constant::CANCELED_BY_SUPPLIER               => ['status' => 'キャンセル済', 'color' => '#FFFFFF;', 'bg_color' => '#575855;'],
                \Constant::CANCELED_BY_TRAVELER               => ['status' => 'キャンセル済', 'color' => '#FFFFFF;', 'bg_color' => '#575855;'],
                \Constant::CANCEL_AWAITING_MANUAL_CALCULATION => ['status' => 'キャンセル済', 'color' => '#FFFFFF;', 'bg_color' => '#575855;'],
                \Constant::CANCEL_AWAITING_CS_CONFIRMATION    => ['status' => 'キャンセル済', 'color' => '#FFFFFF;', 'bg_color' => '#575855;'],
                \Constant::WITHDRAWN_BY_TRAVELER              => ['status' => 'キャンセル済', 'color' => '#FFFFFF;', 'bg_color' => '#575855;'],
                \Constant::REQUESTED                          => ['status' => 'リクエスト中', 'color' => '#575855;', 'bg_color' => '#FFE082;'],
                \Constant::STANDBY                            => ['status' => 'リクエスト中', 'color' => '#575855;', 'bg_color' => '#FFE082;'],
                \Constant::DECLINED                           => ['status' => 'リクエストNG', 'color' => '#575855;', 'bg_color' => '#CECECE;'],
                \Constant::CONFIRMED                          => ['status' => '確定済', 'color' => '#FFFFFF;', 'bg_color' => '#00A17B;'],
                \Constant::CHANGED_BY_SUPPLIER                => ['status' => '確定済 + チェックイン、ピックアップ情報が確定しました。', 'color' => '', 'bg_color' => '']
            ];
            $params = [
                'page'     => (int)$request->get('page', 1),
                'per_page' => (int)$request->get('per_page', 1)
            ];

            $currentBookings = $this->bookingRepository->getCurrentBookingsByAmcAndEmail($user['amc_number'], $user['email'], $params);
            $pastBookings    = $this->bookingRepository->getPastBookingsByAmcAndEmail($user['amc_number'], $user['email'], $params);

            // update plan title, activity title, status and add some properties 'voucher_url', 'required_message'
            if (!empty($currentBookings)) {
                foreach ($currentBookings as $k => $row) {
                    $result = $this->getRequiredDataByBookingIdAndActivityId($row->booking_id, $row->activity_id);
                    $currentBookings[$k]['plan_title']       = $result['plan_title']     ? $result['plan_title']     : $row->plan_title;
                    $currentBookings[$k]['activity_title']   = $result['activity_title'] ? $result['activity_title'] : $row->activity_title;
                    $currentBookings[$k]['voucher_url']      = $result['voucher_url'];
                    $currentBookings[$k]['required_message'] = $result['required_message'];
                    $currentBookings[$k]['status']           = $statuses[$row->status] ?? [];
                }
            }
            
            // update plan title, activity title, status and add some properties 'voucher_url', 'required_message'
            if (!empty($pastBookings)) {
                foreach ($pastBookings as $k => $row) {
                    $result = $this->getRequiredDataByBookingIdAndActivityId($row->booking_id, $row->activity_id);
                    $pastBookings[$k]['plan_title']       = $result['plan_title']     ? $result['plan_title']     : $row->plan_title;
                    $pastBookings[$k]['activity_title']   = $result['activity_title'] ? $result['activity_title'] : $row->activity_title;
                    $pastBookings[$k]['voucher_url']      = $result['voucher_url'];
                    $pastBookings[$k]['required_message'] = $result['required_message'];
                    $pastBookings[$k]['status']           = $statuses[$row->status] ?? [];
                }
            }

            if ($request->ajax()) {
                $type   = $request->get('type', null);
                $result = null;

                switch ($type) {
                    case 'current':
                        $currentList = view('traveler.mypage._current_list', compact('currentBookings'))->render();
                        $result      = response()->json(['current_list' => $currentList]);
                        break;
                    case 'past':
                        $pastList = view('traveler.mypage._past_list', compact('pastBookings'))->render();
                        $result   = response()->json(['past_list' => $pastList]);
                        break;
                    default:
                        $currentList = view('traveler.mypage._current_list', compact('currentBookings'))->render();
                        $pastList    = view('traveler.mypage._past_list', compact('pastBookings'))->render();
                        $result      = response()->json(['current_list' => $currentList, 'past_list' => $pastList]);
                        break;
                }
                return $result;
            }
            return view('traveler.mypage.index', compact('user', 'currentBookings', 'pastBookings'));
        }
        return redirect()->route('traveler.mypage.login-no-ana');
    }

    /**
     * Display the specified resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function showSearchIndex()
    {
        return view('traveler.index');
    }

    /**
     * Login screen without ANA club
     *
     * @return \Illuminate\Http\Response
    */
    public function loginNotAna()
    {
        return view('traveler.mypage.login-no-ana');
    }

    /**
     * Display the specified resource for view.
     *
     * @param  string  $id
     * @return \Illuminate\Http\Response
     */
    public function showBookingDetails($id)
    {
        $user = SessionFake::get('amc_info');

        if (empty($user)) {
            return redirect()->route('traveler.mypage.index');
        }

        $bookingDetailApi  = $this->api->getBookingDetail(['booking_id' => $id]);
        $activityDetailApi = $bookingDetail = $listBookingLogDetail = null;

        if (isset($bookingDetailApi->activity_id) && isset($bookingDetailApi->plan_id)) {
            $activityDetailApi = $this->activityApi->find($bookingDetailApi->activity_id, $bookingDetailApi->plan_id);
            $bookingDetail     = $this->bookingRepository->getDataBookingAndBookingLogById($id);
        }

        if ($activityDetailApi && $bookingDetail) {
            $listBookingLogDetail = $this->bookingLogRepository->getDataById($id);

            $this->addRequiredProperties($bookingDetail, $bookingDetailApi);

            $bookingDetail->log_memo        = $bookingDetail->memo;
            $bookingDetail->date_log_cancel = date('Y-m-d', strtotime($bookingDetail->date_time));
            $bookingDetail->cancel_fee_flag = false;
            $bookingStatusApi = $bookingDetailApi->booking_status ?? null;

            if ($bookingStatusApi == \Constant::REQUESTED || $bookingStatusApi == \Constant::CONFIRMED) {
                $bookingDetail->log_memo = null;
                $bookingDetail->date_log_cancel = null;
            }

            if ($bookingStatusApi == \Constant::CANCELED_BY_TRAVELER || $bookingStatusApi == \Constant::WITHDRAWN_BY_TRAVELER) {
                $bookingDetail->cancel_fee_flag = true;
            }

            // add 'cancel_reason', 'booking_status_jp'
            $bookingDetail->cancel_reason     = config('enums')['cancel_reason'][$bookingStatusApi];
            $bookingDetail->booking_status_jp = config('enums')['booking_status'][$bookingStatusApi];

            // add 'payment_price', 'display_currency_code'
            if (isset($bookingDetailApi->display_currency_code)) {
                $bookingDetail->display_currency_code = $bookingDetailApi->display_currency_code;
                $bookingDetail->payment_price = $bookingDetailApi->display_currency_code.' '.$bookingDetailApi->payment_amount_gross_final;
            }

            // add 'per_participants'
            $plansActivity = $activityDetailApi->plans ?? [];
            $unitNames = $this->getUnitNames($plansActivity)[$bookingDetailApi->plan_id] ?? [];
            $perPBF    = $bookingDetailApi->per_participants_booking_fields ?? [];
            $bookingFields  = $activityDetailApi->booking_fields ?? [];
            $bookingDetail->per_participants = $this->getDataResponsesPerParticipant($perPBF, $unitNames, $bookingFields);
            
            // add 'per_bookings'
            $bookingDetail->per_bookings = $this->getDataResponesPerBooking($bookingDetailApi->per_booking_fields ?? [], $bookingFields);
        }

        if (\Route::current()->getName() == 'traveler.mypage.cancel') {
            if (!empty($bookingDetailApi->cancel_reason)) {
                abort(404);
            }
            return view('traveler.mypage.booking-cancel', compact('bookingDetail'));
        }

        return view('traveler.mypage.details', compact('bookingDetail', 'listBookingLogDetail'));
    }

    /**
     * Display the specified resource for update.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editBooking($id)
    {
        $cancelStatus = [
            $this->enumsBookingStatus[\Constant::CANCELED_BY_SUPPLIER],
            $this->enumsBookingStatus[\Constant::CANCELED_BY_TRAVELER],
            $this->enumsBookingStatus[\Constant::WITHDRAWN_BY_TRAVELER],
            $this->enumsBookingStatus[\Constant::DECLINED],
            $this->enumsBookingStatus[\Constant::CANCEL_AWAITING_MANUAL_CALCULATION],
            $this->enumsBookingStatus[\Constant::CANCEL_AWAITING_CS_CONFIRMATION],
        ];

        $getBookingApi = $this->api->getBookingDetail(['booking_id' => $id]);
        $bookingDetail = $activityDetailApi = $redirectUrl = null;

        // check the booking status whether it is cancelled
        if (in_array($getBookingApi->booking_status, $cancelStatus)) {
            abort(404);
        }

        // get activity details
        if (isset($getBookingApi->activity_id)) {
            $activityDetailApi = $this->activityApi->find($getBookingApi->activity_id, $getBookingApi->plan_id);
            $bookingDetail     = $this->bookingRepository->getBookingAndBookingLogByIdExceptStatuses($id, $cancelStatus);

            // check participation date with current date?
            if (!empty($bookingDetail) && strtotime($bookingDetail->participation_date) < strtotime(date('Y-m-d'))) {
                //$redirectUrl = route('traveler.mypage.booking.details', [$id]);
            }
        }

        // check the booking detail exist?
        if (!empty($bookingDetail) && !empty($activityDetailApi)) {
            $pUI     = $getBookingApi->plan_unit_items ?? [];
            $plans   = isset($activityDetailApi->plans) ? $activityDetailApi->plans[0] : null;
            $abf     = $activityDetailApi->booking_fields ?? [];
            $getPbf  = $getBookingApi->per_booking_fields ?? [];
            $getPpbf = $getBookingApi->per_participants_booking_fields ?? [];

            // hotel info deadline
            $getBookingApi->hotel_info_deadline = $plans->hotel_info_deadline ?? null;

            // get all per_bookings with response from per_booking_fields
            $fieldsPerBooking = array_reduce($abf, function ($carry, $item) use ($getPbf) {
                $out = [];
                $collection = collect($getPbf);
                if ($item->method === \Constant::FIELD_METHOD_PER_BOOKING) {
                    $rowPBF = $collection->where('booking_fields_id', $item->id)->first();
                    if (!empty($rowPBF)) { // add property 'response' if the response exists
                        $item->response = $rowPBF->response;
                    }
                    $out[] = $item;
                }
                return array_merge($carry, $out);
            }, []);

            // get all unit names
            $unitItemNames = collect($plans->price_information_items[0]->unit_items)->pluck('name', 'id')->all();
            //$unitItemNames = $this->getUnitNames($getActivityDetail->plans);
            
            // get all unit items with responses from per_participant_booking_fields
            $unitItems = array_reduce($pUI, function ($carry, $item) use ($unitItemNames, $getPpbf) {
                $arr = [];
                $collection = collect($getPpbf);
                for ($i = 0; $i < $item->count; $i++) {
                    $responses = $collection->where('unit_id', $item->id)->first();
                    $arr[] = (object)[
                        'id'        => $item->id,
                        'gid'       => $i,
                        'name'      => $unitItemNames[$item->id],
                        'responses' => !empty($responses) ? $responses->responses[$i] : null
                    ];
                }
                return array_merge($carry, $arr);
            }, []);

            // get all per_participants from booking_fields through get-activity-details
            $fieldsPerParticipant = array_filter($abf, function ($item) {
                return $item->method === \Constant::FIELD_METHOD_PER_PARTICIPANT;
            });

            if ($resquest->ajax()) {
                $unitItems = createPaginator($unitItems, 1);
                $isPerPariticipants = true;
                $dataHtml = view('traveler.mypage._field', compact('unitItems', 'fieldsPerParticipant', 'isPerPariticipants'))->render();
                return $dataHtml;

                /* add lines below to _field.blade.php:
                    @if ($isPerPariticipants === true)
                    <div>
                        {{ $unitItems->links() }}
                    </div>
                    @endif
                */
                /* add lines below to edit-booking.blade.php:
                    // show per_participants when screen loading
                    $(function () {
                        getPerParticipants('{{ route('traveler.mypage.edit') }}')
                    });

                    // show per_participants when click on every link of pagination
                    $(document).on('click', 'ul.pagination li a', function (e) {
                        e.preventDefault();

                        getPerParticipants($(e.target).attr('href'));
                    });

                    function getPerParticipants(url) {
                        $.ajax({
                            url: url,
                            dataType: 'html',
                            method: 'get',
                            success: function(data) {
                                if (data) {
                                    $('.data-list').html(data);
                                }
                            },
                            error: function (err) {
                                console.log(err);
                            }
                        });
                    }
                */
            }

            return view('traveler.mypage.booking-edit', compact('getBookingApi', 'bookingDetail', 'unitItems', 'fieldsPerBooking', 'fieldsPerParticipant', 'redirectUrl'));
        }
        return redirect()->route('traveler.mypage.index');
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function updateBooking(BookingUpdate $request, $id)
    {
        $perBookingFields = $request->get('per_booking_fields');
        foreach ($perBookingFields as $k => $item) {
            $perBookingFields[$k] = is_array($item) ? (object)$item : $item;
        }
        $perParticipantsBookingFields = $request->get('per_participants_booking_fields');
        foreach ($perParticipantsBookingFields as $k => $item) {
            $obj = is_array($item) ? (object)$item : $item;
            $responses = $obj->responses;
            foreach ($responses as $k2 => $r) {
                $responses[$k2] = is_array($r) ? (object)$r : $r;
            }
            $perParticipantsBookingFields[$k]['responses'] = $responses;
        }

        DB::beginTransaction();
        try {
            if ($request->ajax()) {
                $paramsApi = [
                    'booking_id'                      => $id,
                    'hotel_name'                      => $request->get('hotel_name'),
                    'hotel_address'                   => $request->get('hotel_address'),
                    'hotel_reservation_last_name'     => $request->get('hotel_reservation_last_name'),
                    'hotel_reservation_first_name'    => $request->get('hotel_reservation_first_name'),
                    'hotel_tel'                       => $request->get('hotel_tel'),
                    //'per_booking_fields'              => $request->get('per_booking_fields'),
                    'per_booking_fields'              => $perBookingFields,
                    //'per_participants_booking_fields' => $request->get('per_participants_booking_fields'),
                    'per_participants_booking_fields' => $perParticipantsBookingFields,
                    'arrival_date'                    => $request->get('arrival_date'),
                    'departure_date'                  => $request->get('departure_date'),
                    'flight_number'                   => $request->get('flight_number'),
                    'destination_tel'                 => $request->get('destination_tel'),
                ];

                $updateBooking = $this->api->updateBooking($paramsApi);
                dd($updateBooking);

                // update the booking
                $amcNumber = $request->get('amc_number');
                $email     = $request->get('email');
                $bookingParams = [
                    'amc_number'   => $amcNumber,
                    'contact_mail' => $email,
                ];

                $bookingDetail = Booking::find($id);
                if (!empty($bookingDetail)) {
                    if ($bookingDetail->amc_number == '' && !empty($amcNumber)) {
                        $bookingParams['accumulate_flag'] = 0;
                        $bookingParams['guest_flag'] = 0;
                        $bookingParams['mile_type'] = \Constant::MILE_ACCUMULATION;
                    }
                    //Booking::find($id)->update($bookingParams);
                }

                // update the booking log
                $user  = SessionFake::get('amc_info');
                $memo  = !empty($user) ? $user['role'].' '.$user['username'].' ('.$user['email'].')' : $email;
                $bookingLogParams = [
                    'booking_id'  => $id,
                    'date_time'   => Carbon::now(),
                    'log_name'    => '情報登録・変更',
                    'user'        => 'ANA',
                    'memo'        => $memo,
                    'create_user' => $email,
                    'update_user' => $email,
                ];
                $model = new BookingLog($bookingLogParams);
                //$model->save();
            }
            DB::commit();
            return response()->json(['type' => 'success']);
        } catch (Exception $e) {
            report($e);
            DB::rollBack();
            return false;
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function destroyBooking($id)
    {
        //
    }

    /**
     * logout from ana
     *
     * @return \Illuminate\Http\Response
     */
    public function logout()
    {
        SessionFake::clear('amc_info');
        return redirect()->route('traveler.index');
    }

    /**
     * get the required data by booking id, activity id and plan id from api
     *
     * @param string $bookingID
     * @param string $activityID
     * @param string|null $planID
     * @return array
     */
    private function getRequiredDataByBookingIdAndActivityId($bookingID, $activityID, $planID = null)
    {
        $result = [
            'plan_title'       => null,
            'activity_title'   => null,
            'voucher_url'      => null,
            'required_message' => null
        ];

        $messages = [
            'empty_ok'   => ['message' => '予約必須情報に未記入の項目があります。情報の登録は', 'textLink' => 'こちら', 'bg_color' => '#FFEAE3;', 'border_color' => '#F03D00;'],
            'noempty_ok' => ['message' => 'チェックイン、ピックアップ情報が確定しました。', 'bg_color' => '#ECEFF1;', 'border_color' => '#CFD8DC;']
        ];

        try {
            $bookingDetail  = $this->api->getBookingDetail(['booking_id' => $bookingID]);
            $activityDetail = $this->activityApi->find($activityID);

            if (isset($bookingDetail->booking_id) && isset($activityDetail->id)) {
                $perPBF = isset($bookingDetail->per_participants_booking_fields) &&
                            !empty($bookingDetail->per_participants_booking_fields) ?
                                array_column($bookingDetail->per_participants_booking_fields, 'responses') : [];

                $bookingFields = $activityDetail->booking_fields ?? [];

                // get all booking_fields_id
                $idsBookingFields = [];
                array_filter($perPBF, function ($item) use (&$idsBookingFields) {
                    if (is_array($item) && !empty($item)) {
                        return array_filter($item, function ($r) use (&$idsBookingFields) {
                            if (!in_array($r->booking_fields_id, $idsBookingFields)) {
                                $idsBookingFields[] = $r->booking_fields_id;
                            }
                        });
                    }
                });

                // check REQUIRED_BY_ACTIVITY_DATE whether it exists in booking_fields from get-activity-details
                $confirmByActivityDate = false;
                if (!empty($bookingFields) && !empty($perPBF) && !empty($idsBookingFields)) {
                    $exists = array_filter($bookingFields, function ($item) use ($idsBookingFields) {
                        return in_array($item->id, $idsBookingFields) &&
                                    $item->method === \Constant::FIELD_METHOD_PER_PARTICIPANT &&
                                        $item->type === \Constant::FIELD_TYPE_REQUIRED_BY_ACTIVITY_DATE;
                    });
                    $confirmByActivityDate = !empty($exists) ? true : false;
                }

                // get the required messages
                if (empty($perPBF)) { // && $confirmByActivityDate
                    $result['required_message'] = $messages['empty_ok'];
                } else if (!empty($perPBF) && $confirmByActivityDate) {
                    $result['required_message'] = $messages['noempty_ok'];
                }
                $result['voucher_url']     = $bookingDetail->voucher_url ?? null;
                $result['plan_title']      = $bookingDetail->plan_title ?? null;
                $result['activity_title']  = $activityDetail->title ?? null;
            }
            return $result;
        } catch (\Exception $e) {
            return $result;
        }
    }

    /**
     * add properties to object
     *
     * @param object $bookingDetail
     * @param object $activityDetailApi
     * @return void
     */
    private function addRequiredProperties(&$bookingDetail, $activityDetailApi)
    {
        $properties = [
            'target_date', 'voucher_url', 'participant_first_name', 'participant_last_name',
            'activity_title', 'activity_id', 'plan_title', 'plan_unit_items',
            'plan_options',   'plan_transportation_item', 'booked_date', 'plan_start_time',
            'display_amount_gross_final', 'activity_cancel_policies', 'checkin_date',
            'checkin_time', 'checkin_location_title', 'checkin_location_description',
            'pick_up_date', 'pick_up_time', 'pick_up_location_title', 'pick_up_location_description',
            'hotel_name', 'hotel_address', 'hotel_tel', 'hotel_reservation_first_name',
            'hotel_reservation_last_name', 'arrival_date', 'departure_date', 'flight_number',
            'destination_tel',
        ];
        $defArray = ['plan_unit_items', 'plan_options', 'activity_cancel_policies'];
        
        // add properties dynamically
        foreach ($properties as $property) {
            $bookingDetail->{$property} = isset($activityDetailApi->{$property}) ? $activityDetailApi->{$property} : (in_array($property, $defArray) ? [] : null);
        }
    }

    /**
     * get all unit names from plan
     *
     * @param  array $plans
     * @return array
     */
    private function getUnitNames($plans = [])
    {
        $out = [];
        foreach ($plans as $plan) {
            $priceInfoItems = $plan->price_information_items ?? [];
            $outUnitNames = [];
            foreach ($priceInfoItems as $item) {
                $unitItems = $item->unit_items ?? [];
                foreach ($unitItems as $row) {
                    $outUnitNames[$row->id] = $row->name;
                }
            }
            $out[$plan->id] = $outUnitNames;
        }
        return $out;
    }

    /**
     * get data response perBooking to show in detail
     * @param array $perBookingFields
     * @param array $activityBookingFields
     * @param boolean $$methodPerBookingRequired
     * @return  array
     */
    private function getDataResponesPerBooking($perBookingFields, $activityBookingFields, $requirePerBookingMethod = true)
    {
        $countPBF = count($perBookingFields);
        $countABF = count($activityBookingFields);
        $out = [];
        for ($i = 0; $i < $countPBF; $i++) {
            for ($j = 0; $j < $countABF; $j++) {
                $isPerBookingMethod = $requirePerBookingMethod === true ? $activityBookingFields[$j]->method == \Constant::FIELD_METHOD_PER_BOOKING : true;
                if ($perBookingFields[$i]->booking_fields_id == $activityBookingFields[$j]->id && $isPerBookingMethod) {
                    $out[] = [
                        'id'           => $activityBookingFields[$j]->id ?? null,
                        'title'        => $activityBookingFields[$j]->title ?? null,
                        'method'       => $activityBookingFields[$j]->method ?? null,
                        'type'         => $activityBookingFields[$j]->type ?? null,
                        'format'       => $activityBookingFields[$j]->format ?? null,
                        'choices'      => $activityBookingFields[$j]->choices ?? [],
                        'description'  => $activityBookingFields[$j]->description ?? null,
                        'response'     => $perBookingFields[$i]->response ?? null,
                        'responseText' => isset($perBookingFields[$i]->response) && is_array($perBookingFields[$i]->response) ? implode('<br>', $perBookingFields[$i]->response) : ''
                    ];
                }
            }
        }
        return $out;
    }

    /**
     * get data response perBooking to show in detail
     * @param array $abf
     * @param array $activityBookingField
     * @return  array
     */
    private function getDataResponesPerBooking2($perBookingFields, $activityBookingField)
    {
        for ($i = 0; $i < count($perBookingFields); $i++) {
            for ($j = 0; $j < count($activityBookingField); $j++) {
                if ($perBookingFields[$i]->booking_fields_id == $activityBookingField[$j]->id) {
                    $perBookingFields[$i]->id          = $activityBookingField[$j]->id;
                    $perBookingFields[$i]->title       = $activityBookingField[$j]->title ?? null;
                    $perBookingFields[$i]->method      = $activityBookingField[$j]->method ?? null;
                    $perBookingFields[$i]->type        = $activityBookingField[$j]->type ?? null;
                    $perBookingFields[$i]->format      = $activityBookingField[$j]->format ?? null;
                    $perBookingFields[$i]->description = $activityBookingField[$j]->description ?? null;
                }
            }
        }
        return $perBookingFields;
    }

    /**
     * get data response perParticipant to show in detail
     * @param array $abf
     * @param array $getPpbf
     * @return  array
     */
    private function getDataResponsesPerParticipant($perParticipants = [], $unitNames = [], $bookingFields = [])
    {
        $out = [];
        $bookingFields = $bookingFields instanceof Collection ? $bookingFields : collect($bookingFields);
        foreach ($perParticipants as $row) {
            $responses = $row->responses ?? [];
            foreach ($responses as $response) {
                // filter by field 'id' and 'method'
                $bookingField = $bookingFields->where('id', $response->booking_fields_id)
                                              ->where('method', \Constant::FIELD_METHOD_PER_PARTICIPANT)
                                              ->first();
                $out[] = [
                    'unit_id'      => $row->unit_id,
                    'unit_name'    => $unitNames[$row->unit_id] ?? null,
                    'title'        => $bookingField->title ?? null,
                    'method'       => $bookingField->method ?? null,
                    'type'         => $bookingField->type ?? null,
                    'format'       => $bookingField->format ?? null,
                    'choices'      => $bookingField->choices ?? [],
                    'description'  => $bookingField->description ?? null,
                    'response'     => $response->response ?? [],
                    'responseText' => implode(' - ', $response->response ?? [])
                ];
            }
        }
        return $out;
    }

    /**
     * get data response perParticipant to show in detail
     * @param array $abf
     * @param array $getPpbf
     * @return  array
     */
    private function getDataResponsesPerParticipant2($abf, $getPpbf, $unitItems = [], $unitItemNames = [])
    {
        for ($i = 0; $i < count($getPpbf); $i++) {
            for ($j = 0; $j < count($getPpbf[$i]->responses); $j++) {
                for ($k = 0; $k < count($abf); $k++) {
                    if ($getPpbf[$i]->responses[$j]->booking_fields_id == $abf[$k]->id && $abf[$k]->method == 'PER_PARTICIPANT') {
                        $getPpbf[$i]->responses[$j]->title       = $abf[$k]->title ?? null;
                        $getPpbf[$i]->responses[$j]->method      = $abf[$k]->method ?? null;
                        $getPpbf[$i]->responses[$j]->type        = $abf[$k]->type ?? null;
                        $getPpbf[$i]->responses[$j]->format      = $abf[$k]->format ?? null;
                        $getPpbf[$i]->responses[$j]->description = $abf[$k]->description ?? null;
                    }
                }
                for ($h= 0; $h < count($unitItems); $h++) {
                    if ($getPpbf[$i]->unit_id == $unitItems[$h]['id']) {
                        $getPpbf[$i]->responses[$j]->unit_name = $unitItems[$h]['name'];
                    }
                }
            }
        }
        return $getPpbf;
    }

    /**
     * fake login by session
     *
     * @param string $amc_number
     * @return void
     */
    public function fakeLoginBySession($amc_number)
    {
        if ($amc_number) {
            $email = 'stevennguyen@gmail.com';
            $amcInfo = [
                'amc_number'        => $amc_number,
                'ana_account'       => 'ANA-CLUB-ACCOUNT'.rand(1, 100),
                'username'          => 'ANA-CLUB-ACCOUNT'.rand(1, 100),
                'first_name'        => 'Steven',
                'last_name'         => 'Nguyen',
                'balance'           => rand(1000, 20000),
                'email'             => $email,
                'member_tier_level' => 'VIP',
                'role'              => 'VIP',
                'country'           => 'Japan',
                'language'          => 'jp'
            ];
            SessionFake::set('amc_info', $amcInfo);
            return redirect('/mypage');
        }
        return redirect()->route('/');
    }

    /**
     * Generate pagination of items in an array or collection.
     *
     * @param array|Collection $items   []
     * @param int $perPage              []
     * @param int $page                 []
     * @param array $options            []
     *
     * @return LengthAwarePaginator
     */
    private function createPaginator($items, $perPage = 15, $page = null, $options = [])
    {
        $items = $items instanceof \Illuminate\Support\Collection ? $items : \Illuminate\Support\Collection::make($items);
        $page  = \Request::get('page', 1); // \Illuminate\Pagination\Paginator::resolveCurrentPage()
        $path  = \Request::url();          // \Illuminate\Pagination\Paginator::resolveCurrentPath()
        return new \Illuminate\Pagination\LengthAwarePaginator($items->forPage($page, $perPage), $items->count(), $perPage, $page, ['path' => $path], $options);
    }
}
