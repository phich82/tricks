<?php

namespace Tests\Unit\MyPage;

use Mockery;
use App\Api\Veltra;
use Tests\TestCase;
use Tests\StubSSOData;
use App\Api\BookingApi;
use App\Models\Booking;
use App\Models\BookingLog;
use Illuminate\Support\Facades\DB;
use App\Repositories\BookingRepository;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Screen: Traveler_MyPage_(MyBooking)_ListScreen
 * @author Phat Huynh Nguyen <huynh.phat@mulodo.com>
 */
class MyPageControllerTest extends TestCase
{
    use RefreshDatabase;
    use StubSSOData;

    /**
     * @var $url
     */
    private static $url;

    /**
     * @var bookingRepository
     */
    private $bookingRepo;

    private $api;

    /**
     * @inheritDoc
     */
    public function setUp()
    {
        parent::setUp();

        $this->bookingRepo = new BookingRepository();
        self::$url = route('traveler.mypage.booking.edit');
        self::fakeSessionSSO();
    }

    /**
     * clean up the testing environment before the next test
     *
     * @return void
     */
    public function tearDown()
    {
        if ($this->app) {
            foreach ($this->beforeApplicationDestroyedCallbacks as $callback) {
                call_user_func($callback);
            }
            $this->app->flush();
            $this->app = null;
        }

        if (class_exists('Mockery')) {
            Mockery::close();
        }
    }

    /**
     * create bookings for test
     *
     * @param integer $totalRecords
     * @param string  $bookingDate
     * @param integer $limit
     * @return void
     */
    private function createBookings($totalRecords = 1, $bookingDate = null, $limit = 1)
    {
        $totalRecords = is_int($totalRecords) && $totalRecords > 1 ? $totalRecords : 1;
        $limit = is_int($limit) && $limit > 1 ? $limit : 1;
        $data = [
            [
                'booking_id'            => 'VELTRA-5YJOYFNT',
                'activity_id'           => 'VELTRA-100010679',
                'plan_id'               => 'VELTRA-108951-0',
            ],
            [
                'booking_id'            => 'VELTRA-3N0RHQ74',
                'activity_id'           => 'VELTRA-100010613',
                'plan_id'               => 'VELTRA-108775-0',
            ],
            [
                'booking_id'            => 'VELTRA-43HS4NG7',
                'activity_id'           => 'VELTRA-100010679',
                'plan_id'               => 'VELTRA-108951-0',
            ],
            [
                'booking_id'            => 'VELTRA-J3PYAXCH',
                'activity_id'           => 'VELTRA-100010655',
                'plan_id'               => 'VELTRA-108896-0',
            ]
        ];
        
        for ($i = 0; $i < $totalRecords; $i++) {
            $randomData = $i < 4 ? $data[$i] : [
                'booking_id'            => 'VELTRA-'.$i,
                'activity_id'           => 'VELTRA-0000'.$i,
                'plan_id'               => 'VELTRA-1111'.$i,
            ];

            if (empty($bookingDate) || (!empty($bookingDate) && $i >= $limit)) {
                $bookingDate = date('Y-m-d', strtotime(date('Y-m-d')) - ($i+1)*24*60*60);
            }

            factory(Booking::class)->create([
                'booking_id'            => $randomData['booking_id'],
                'activity_id'           => $randomData['activity_id'],
                'plan_id'               => $randomData['plan_id'],
                'guest_flag'            => 1,
                'first_name'            => 'Steven',
                'last_name'             => 'Nguyen',
                'email'                 => 'fake@gmail.com',
                'contact_mail'          => 'admin@test.com',
                'amc_number'            => 'AMC75',
                'booking_date'          => $bookingDate,
                'participation_date'    => '2007-01-09',
                'participant_persons'   => rand(1, 100),
                'sales_price'           => '400',
                'booking_unit_price'    => '4',
                'refund_mile'           => '0',
                'mile_type'             => rand(0, 2),
                'accumulation_type'     => rand(0, 2),
                'accumulate_flag'       => rand(0, 1),
                'status'                => 'CONFIRMED',
                'create_user'           => 'admin',
                'update_user'           => 'admin'
            ]);
        }
    }

    /**
     * create the booking by booking date and params for test
     *
     * @param  string  $bookingDate
     * @param  array   $params
     * @param  boolean $returned
     * @return void|object
     */
    private function createBookingByDateAndParams($bookingDate, $params = [], $returned = false)
    {
        $params = is_array($params) && !empty($params) ? $params : [
            'booking_id'   => 'VELTRA-5YJOYFNT',
            'activity_id'  => 'VELTRA-100010679',
            'plan_id'      => 'VELTRA-108951-0',
        ];

        $booking = factory(Booking::class)->create([
            'booking_id'            => $params['booking_id'],
            'activity_id'           => $params['activity_id'] ?? 'VELTRA-100010679',
            'plan_id'               => $params['plan_id']     ?? 'VELTRA-108951-0',
            'guest_flag'            => 1,
            'first_name'            => $params['first_name'] ?? 'Steven',
            'last_name'             => $params['last_name']  ?? 'Nguyen',
            'email'                 => $params['email']      ?? 'fake@gmail.com',
            'contact_mail'          => 'admin@test.com',
            'amc_number'            => $params['amc_number'] ?? 'AMC75',
            'booking_date'          => $bookingDate,
            'participation_date'    => '2007-01-09',
            'participant_persons'   => rand(1, 100),
            'sales_price'           => '400',
            'booking_unit_price'    => '4',
            'refund_mile'           => '0',
            'mile_type'             => rand(0, 2),
            'accumulation_type'     => rand(0, 2),
            'accumulate_flag'       => rand(0, 1),
            'status'                => $params['status'] ?? 'CONFIRMED',
            'create_user'           => 'admin',
            'update_user'           => 'admin'
        ]);

        if ($returned === true) {
            return $booking;
        }
    }

    /**
     * create the booking log for test
     *
     * @param  string  $bookingID
     * @param  boolean $returned
     * @return void|object
     */
    private function createBookingLog($bookingID, $returned = false)
    {
        $bookingLog = factory(BookingLog::class)->create([
            'booking_id'  => $bookingID,
            'date_time'   => '2005-08-16 20:39:21',
            'log_name'    => 'log test',
            'memo'        => 'test_comment',
            'user'        => 'test',
            'create_user' => 'admin',
            'update_user' => 'admin'
        ]);

        if ($returned === true) {
            return $bookingLog;
        }
    }

    /**
     * get url by paramters
     *
     * @param array $params
     * @return string
     */
    private function getUrlByParams($params = [])
    {
        return is_array($params) && !empty($params) ? route('traveler.mypage.booking.edit', $params) : self::$url;
    }

    /**
     * mock restful api
     *
     * @param array $params
     * @return void
     */
    private function mockApi($params = [], $flag = false)
    {
        $resBooking1 = (object)[
            "common"                          => (object)["status_code" => 200],
            "booking_id"                      => "VELTRA-3N0RHQ74",
            "booking_status"                  => "CANCELED_BY_TRAVELER",
            "activity_id"                     => "VELTRA-100010613",
            "activity_title"                  => "scenario2-single package voucher-JP",
            "plan_id"                         => "VELTRA-108775-0",
            "voucher_url"                     => "https://storage.googleapis.com/dev-voucher.vds-connect.com/vouchers/1598181/aa17921456fcaf3c.pdf",
            "per_participants_booking_fields" => [
                (object)[
                    "unit_id" => "147000",
                    "responses" => [
                        (object)[
                            "booking_fields_id" => "31396",
                            "response" => [
                                "Le Dinh Huy 1"
                            ]
                        ],
                        (object)[
                            "booking_fields_id" => "31396",
                            "response" => [
                                "Le Dinh Huy 2"
                            ]
                        ]
                    ]
                ],
                (object)[
                    "unit_id" => "147002",
                    "responses" => [
                        (object)[
                            "booking_fields_id" => "31396",
                            "response" => [
                                "Le Dinh Huy 3"
                            ]
                        ],
                        (object)[
                            "booking_fields_id" => "31396",
                            "response" => [
                                "Le Dinh Huy 4"
                            ]
                        ]
                    ]
                ]
            ],
        ];
        $resBooking2 = (object)[
            "common" => (object)["status_code" => 200],
            "booking_id" => "VELTRA-43HS4NG7",
            "booking_status" => "WITHDRAWN_BY_TRAVELER",
            "activity_id" => "VELTRA-100010679",
            "activity_title" => "scenario22-single package voucher-JP",
            "plan_id" => "VELTRA-108951-0",
            "per_booking_fields" => [
                (object)[
                    "booking_fields_id" => "31566",
                    "response" => [
                        "sea"
                    ]
                ]
            ],
        ];
        $resBooking3 = (object)[
            "common"             => (object)["status_code" => 200],
            "booking_id"         => "VELTRA-5YJOYFNT",
            "booking_status"     => "REQUESTED",
            "activity_id"        => "VELTRA-100010679",
            "activity_title"     => "scenario22-single package voucher-JP",
            "plan_id"            => "VELTRA-108951-0",
            "per_booking_fields" => [
                (object)[
                    "booking_fields_id" => "31566",
                    "response" => [
                        "sea"
                    ]
                ]
            ]
        ];
        $resBooking4 = (object)[
            "common"                          => (object)['status_code' => 200],
            "booking_id"                      => "VELTRA-J3PYAXCH",
            "booking_status"                  => "CONFIRMED",
            "activity_id"                     => "VELTRA-100010655",
            "activity_title"                  => "scenario9-multiple package voucher-EN",
            "plan_id"                         => "VELTRA-108896-0",
            "voucher_url"                     => "https://storage.googleapis.com/dev-voucher.vds-connect.com/vouchers/1597797/15a4de833ca5c0d6.pdf",
            "per_participants_booking_fields" => [
                (object)[
                    "unit_id" => "146583",
                    "responses" => [
                        (object)[
                            "booking_fields_id" => "31478",
                            "response" => [
                                "string1"
                            ]
                        ],
                        (object)[
                            "booking_fields_id" => "31478",
                            "response" => [
                                "string2"
                            ]
                        ],
                        (object)[
                            "booking_fields_id" => "31478",
                            "response" => [
                                "string3"
                            ]
                        ],
                        (object)[
                            "booking_fields_id" => "31478",
                            "response" => [
                                "string4"
                            ]
                        ],
                        (object)[
                            "booking_fields_id" => "31478",
                            "response" => [
                                "string5"
                            ]
                        ]
                    ]
                ]
            ],
        ];
        $booking_ids = [
            'VELTRA-3N0RHQ74' => $resBooking1,
            'VELTRA-43HS4NG7' => $resBooking2,
            'VELTRA-5YJOYFNT' => $resBooking3,
            'VELTRA-J3PYAXCH' => $resBooking4,
        ];

        $resActivity1 = (object)[
            "common"         => (object)["status_code" => 200],
            "booking_fields" => [
                (object)[
                    "id"       => "31396",
                    "method"   => "PER_PARTICIPANT",
                    "type"     => "REQUIRED_ON_BOOKING",
                    "format"   => "TEXT",
                    "title"    => "参加者氏名（パスポート表記と同じローマ字でご入力ください　※半角英数）",
                    "plan_ids" => [
                        "VELTRA-108775-0"
                    ]
                ],
                (object)[
                    "id"      => "31398",
                    "method"  => "PER_PARTICIPANT",
                    "type"    => "OPTIONAL",
                    "format"  => "SELECT_ONE",
                    "title"   => "性別",
                    "choices" => [
                        "男性",
                        "女性"
                    ],
                    "plan_ids" => [
                        "VELTRA-108775-0"
                    ]
                ],
                (object)[
                    "id"       => "31591",
                    "method"   => "PER_BOOKING",
                    "type"     => "REQUIRED_BY_ACTIVITY_DATE",
                    "format"   => "TEXT",
                    "title"    => "ご希望の観光スポット",
                    "plan_ids" => [
                        "VELTRA-108775-0"
                    ]
                ]
            ]
        ];
        $resActivity2 = (object)[
            "common"         => (object)["status_code" => 200],
            "booking_fields" => [
                (object)[
                    "id"       => "31564",
                    "method"   => "PER_PARTICIPANT",
                    "type"     => "REQUIRED_BY_ACTIVITY_DATE",
                    "format"   => "YES_OR_NO",
                    "title"    => "ベジタリアンフード希望",
                    "choices"  => [
                        "あり",
                        "なし"
                    ],
                    "plan_ids" => [
                        "VELTRA-108951-0"
                    ]
                ],
                (object)[
                    "id"       => "31565",
                    "method"   => "PER_PARTICIPANT",
                    "type"     => "OPTIONAL",
                    "format"   => "TEXT",
                    "title"    => "食物アレルギー",
                    "plan_ids" => [
                        "VELTRA-108951-0"
                    ]
                ],
                (object)[
                    "id"       => "31566",
                    "method"   => "PER_BOOKING",
                    "type"     => "REQUIRED_ON_BOOKING",
                    "format"   => "TEXT",
                    "title"    => "ご希望の観光スポット",
                    "plan_ids" => [
                        "VELTRA-108951-0"
                    ]
                ]
            ],
            "plans" => [
                (object)[
                    "price_information_items" => [
                        (object)[
                            "unit_items" => [
                                (object)[
                                    "id" => "146763",
                                    "name" => "大人子供共通 (5歳以上)",
                                    "original_amount" => 1000,
                                    "display_amount" => 1000,
                                    "criteria" => "STANDALONE_AND_COUNTABLE"
                                ]
                            ]
                        ]
                    ],
                ]
            ]
        ];
        $resActivity4 = (object)[
            "common" => (object)['status_code' => 404],
        ];
        $activity_ids = [
            'VELTRA-100010613' => $resActivity1,
            'VELTRA-100010679' => $resActivity2,
            'VELTRA-100010679' => $resActivity2,
            'VELTRA-100010655' => $resActivity4,
        ];

        $m = Mockery::mock(BookingApi::class);

        if (array_key_exists('booking_id', $params) && !array_key_exists('activity_id', $params)) { // fake api 'get-booking-details'
            $returned = array_search($params['booking_id'], array_keys($booking_ids)) === false ?
                            (object)['common' => (object)['status_code' => 404]] :
                                $booking_ids[$params['booking_id']];

            $m->shouldReceive('getBookingDetail')
              ->with(['booking_id' => $params['booking_id']])
              ->andReturn($returned);
        } elseif (!array_key_exists('booking_id', $params) && array_key_exists('activity_id', $params)) { // fake api 'get-activity-details'
            $returned = array_search($params['activity_id'], array_keys($activity_ids)) === false ?
                            (object)['common' => (object)['status_code' => 404]] :
                                $activity_ids[$params['activity_id']];

            $m->shouldReceive('getActivityDetail')
              ->with(['activity_id' => $params['activity_id']])
              ->andReturn($returned);
        } elseif (array_key_exists('booking_id', $params) && array_key_exists('activity_id', $params)) {
            $returnedBooking  = $booking_ids[$params['booking_id']];
            $returnedActivity = $activity_ids[$params['activity_id']];

            if ($flag === true) {
                $this->addProperties($returnedBooking);
            } elseif ($flag === 'empty_ok') {
                if (isset($returnedBooking->per_participants_booking_fields)) {
                    $returnedBooking->per_participants_booking_fields[0]->responses = [];
                } else {
                    $returnedBooking->per_participants_booking_fields = [(object)['responses' => []]];
                }
                $types = $returnedActivity->booking_fields ?? [];
                if (!empty($types)) {
                    foreach ($types as $k => $type) {
                        $returnedActivity->booking_fields[$k]->method = 'PER_PARTICIPANT';
                        $returnedActivity->booking_fields[$k]->type = 'REQUIRED_BY_ACTIVITY_DATE';
                    }
                }
            }

            $m->shouldReceive('getBookingDetail')
              ->with(['booking_id' => $params['booking_id']])
              ->andReturn($returnedBooking);

            $paramsActivity = array_key_exists('plan_id', $params) ? ['activity_id' => $params['activity_id'], 'plan_id' => $params['plan_id']] : ['activity_id' => $params['activity_id']];
            $m->shouldReceive('getActivityDetail')
              ->with($paramsActivity)
              ->andReturn($returnedActivity);
        }
        $this->app->instance(BookingApi::class, $m);
    }

    /**
     * add the extra properties (keys) to json object from api for test
     *
     * @param  object $objReceive
     * @return void
     */
    private function addProperties(&$objReceive)
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
            $objReceive->{$property} = in_array($property, $defArray) ? [] : null;
        }
    }
}