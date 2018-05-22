<?php

use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    $fielsRequired = [
        'hotel_name',
        'hotel_address',
        'hotel_participant_first_name',
        'hotel_participant_last_name',
        'per_booking_fields[0][response][0].REQUIRED_ON_BOOKING>This is title 1',
        'per_booking_fields[1][response][0].REQUIRED_ON_BOOKING>This is title 2',
        'per_participants_booking_fields[0][responses][0][response][0].YES_NO.YES,NO>This is title 2',
    ];

    //dd(createRules($fielsRequired));

    $ppbf = [
        (object)[
            'unit_id' => '17000',
            'responses' => [
                'Sea1'
            ]
        ],
        (object)[
            'unit_id' => '18000',
            'responses' => [
                'Sea2'
            ]
        ]
    ];
    $pUI = [
        (object)[
            'id' => '17000',
            'name' => 'Title 1',
            'count' => 2
        ],
        (object)[
            'id' => '16000',
            'name' => 'Title 2',
            'count' => 2
        ]
    ];
    $arr = array_reduce($pUI, function ($carry, $item) use ($ppbf) {
        $out = [];
        $collection = collect($ppbf);
        for ($i=0; $i < count($item->count); $i++) {
            $rowPPBF = $collection->where('unit_id', $item->id)->first();
            $out[] = [
                'id' => $item->id,
                'name' => $item->name,
                'responses' => !empty($rowPPBF) ? $rowPPBF->responses : []
            ];
        }
        return array_merge($carry, $out);
    }, []);

    //dd($arr);

    $items = [
        ['id' => 1, 'name' => 'A'],
        ['id' => 2, 'name' => 'B'],
        ['id' => 3, 'name' => 'C'],
        ['id' => 4, 'name' => 'D'],
        ['id' => 5, 'name' => 'E'],
    ];
    $perParticipants = [
        [
            'id' => 1,
            'total' => 2
        ],
        [
            'id' => 2,
            'total' => 5
        ],
        [
            'id' => 3,
            'total' => 6
        ],
        [
            'id' => 4,
            'total' => 2
        ],
        [
            'id' => 5,
            'total' => 2
        ]
    ];

    $list = createPaginator($items, 2);

    if (\Request::ajax()) {
        return view('_list', compact('list', 'perParticipants'))->render();
    }
    return view('welcome', compact('list'));
});

Route::post('/test', function (\App\Http\Requests\TestRequest $request) {
    return response()->json(['success' => true, 'fieldsRequired' => $request->get('fieldsRequired'), 'input' => $request->all()]);
})->name('test');

function createRules($fieldsRequired = [])
{
    $mapRules = [
        'DATE' => 'date_format:"Y-m-d"|nullable',
        'TIME' => 'date_format:"H:i"|nullable',
        'YES_NO' => 'in:YES,NO|nullable',
        'SELECT_ONE' => 'in:YES,NO|nullabe',
        'SELECT_MULTIPLE' => 'in:|nullable',
    ];

    $formatsTypeTequired = ['REQUIRED_ON_BOOKING', 'REQUIRED_BOOKING_ACTIVITY_DATE'];

    $rules = [];
    foreach ($fieldsRequired as $field) {
        if (stripos($field, '[') !== false) {
            $split = explode('>', $field);
            $first = $split[0];
            $title = substr($field, strlen($first) + 1);
            $splitField = explode('.', $first);
            $fieldName  = $splitField[0];
            $formatType = strtoupper($splitField[1]);
            $choices    = isset($splitField[2]) ? $splitField[2] : null;

            if (stripos($field, 'per_booking_fields') !== false) {
                $rules['per_booking_fields.*.unit_id'] = 'required|string';
                $rules['per_booking_fields.*.response'] = 'array';
                if (in_array($formatType, $formatsTypeTequired)) {
                    $rules['per_booking_fields.*.response.*'] = 'required';
                } elseif (in_array($formatType, $mapRules)) {
                    $rules['per_booking_fields.*.response.*'] = $mapRules[$formatType];
                }
            } elseif (stripos($field, 'per_participants_booking_fields') !== false) {
                $rules['per_participants_booking_fields.*.*.booking_fields_id'] = 'required|string';
                $rules['per_participants_booking_fields.*.*.responses'] = 'array';
                if (in_array($formatType, $formatsTypeTequired)) {
                    $rules['per_participants_booking_fields.*.*.responses.response.*'] = 'required';
                } elseif (in_array($formatType, $mapRules)) {
                    $rules['per_participants_booking_fields.*.*.responses.response.*'] = $mapRules[$formatType];
                }
            }
        } else {
            $rules[$field] = 'required';
        }
    }

    return $rules;
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
function createPaginator($items, $perPage = 15, $page = null, $options = [])
{
    $items = $items instanceof \Illuminate\Support\Collection ? $items : \Illuminate\Support\Collection::make($items);
    $page  = \Request::get('page', 1); // \Illuminate\Pagination\Paginator::resolveCurrentPage()
    $path  = \Request::url();          // \Illuminate\Pagination\Paginator::resolveCurrentPath()
    return new \Illuminate\Pagination\LengthAwarePaginator($items->forPage($page, $perPage), $items->count(), $perPage, $page, ['path' => $path], $options);
}

function pagination($items)
{
    // Get current page form url e.x. &page=1
    $currentPage = Illuminate\Pagination\LengthAwarePaginator::resolveCurrentPage();
 
    // Create a new Laravel collection from the array data
    $itemCollection = $items instanceof Collection ? $items : collect($items);

    // Define how many items we want to be visible in each page
    $perPage = 1;

    // Slice the collection to get the items to display in current page
    $currentPageItems = $itemCollection->slice(($currentPage * $perPage) - $perPage, $perPage)->all();

    // Create our paginator and pass it to the view
    $paginatedItems = new Illuminate\Pagination\LengthAwarePaginator($currentPageItems, count($itemCollection), $perPage);

    // set url path for generted links
    $paginatedItems->setPath(Request::url());

    return $paginatedItems;
}
