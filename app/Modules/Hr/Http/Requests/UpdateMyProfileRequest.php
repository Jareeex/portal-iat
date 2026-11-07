<?php

namespace App\Modules\Hr\Http\Requests;

use App\Modules\Hr\Support\EmployeeRules;
use Illuminate\Foundation\Http\FormRequest;

/** Self-service: karyawan cuma boleh ubah data diri, bukan data kepegawaian. */
class UpdateMyProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return EmployeeRules::personal();
    }
}
