<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AdminIssueTicketRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth('enforcer')->check() || auth('admin')->check();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $nameRegex = "/^[A-Za-zÀ-ÖØ-öø-ÿ\s.\-']+$/u";
        $licenseRegex = "/^[A-Z]\d{2}-\d{2}-\d{6}$/";
        return [
            'first_name'  => ['required','string','max:50',"regex:$nameRegex"],
            'middle_name' => ['nullable','string','max:50',"regex:$nameRegex"],
            'last_name'   => ['required','string','max:50',"regex:$nameRegex"],

            'license_num' => ['required','string','max:13',"regex:$licenseRegex"],

            'address'     => ['nullable','string','max:255'],
            'birthdate'   => ['nullable','date'],

            'plate_num'   => ['nullable','string','max:16'], // keep flexible; PH plates vary
            'vehicle_type'=> ['required','string','max:32'],

            'is_owner'    => ['nullable','boolean'],
            'owner_name'  => ['nullable','string','max:80',"regex:$nameRegex"],
            'flags'         => 'array',           // you can also validate an incoming flags[] if you switch to that
            'flags.*'       => 'exists:flags,id',
            'confiscation_type_id' => ['nullable','integer','exists:confiscation_types,id'],

            'location'    => ['required','string','max:120'],
            'latitude'    => ['nullable','numeric','between:-90,90'],
            'longitude'   => ['nullable','numeric','between:-180,180'],

            'violations'  => ['nullable','array'],
            'violations.*'=> ['string','max:50'],

            'client_uuid' => ['nullable','string','max:64'],
            'enforcer_id' => ['nullable','integer','exists:enforcers,id'],
        ];
    }
    public function messages(): array
    {
        return [
            'first_name.regex' => 'First name may contain letters, spaces, dot, apostrophe, and hyphen only.',
            'middle_name.regex'=> 'Middle name may contain letters, spaces, dot, apostrophe, and hyphen only.',
            'last_name.regex'  => 'Last name may contain letters, spaces, dot, apostrophe, and hyphen only.',
            'owner_name.regex' => 'Owner name may contain letters, spaces, dot, apostrophe, and hyphen only.',
            'license_num.regex'=> 'License must match the format A12-34-567890.',
        ];
    }
}
