<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TestRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $mapRules = [
            'DATE' => 'date_format:"Y-m-d"|nullable',
            'TIME' => 'date_format:"H:i"|nullable',
            'YES_NO' => 'in:YES,NO|nullable',
            'SELECT_ONE' => 'in:YES,NO|nullabe',
            'SELECT_MULTIPLE' => 'in:|nullable',
        ];
    
        $formatsTypeTequired = ['REQUIRED_ON_BOOKING', 'REQUIRED_BOOKING_ACTIVITY_DATE'];
        $fieldsRequired = $this->get('fieldsRequired');
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
        dd($rules);
        return $rules;
    }
}
