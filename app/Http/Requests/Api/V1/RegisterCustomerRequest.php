<?php

namespace App\Http\Requests\Api\V1;

use App\Core\Models\PartnerCode;
use App\Core\Models\Setting;
use App\Services\ReferralService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class RegisterCustomerRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:customers,email'],
            'phone' => ['nullable', 'string', 'max:20', 'unique:customers,phone'],
            'country_code' => ['nullable', 'string', 'max:5', 'regex:/^\+[0-9]{1,4}$/'],
            'birthdate' => ['nullable', 'date', 'before:today'],
            'password' => ['required', 'string', 'min:8', 'confirmed', 'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[#?!@$%^&*-])[A-Za-z\d#?!@$%^&*-]{8,}$/'],
            'avatar' => ['nullable', 'image', 'max:2048'],
            'referral_code' => ['nullable', 'string', 'max:30'],
            'partner_code' => ['nullable', 'string', 'max:40'],
            'signup_code' => ['nullable', 'string', 'max:40'],
        ];
    }

    /**
     * partner_code must be an active partner code. signup_code is the
     * single app field: a partner code first, else a referral code.
     */
    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            if (filled($this->input('partner_code')) && ! PartnerCode::findActive($this->input('partner_code'))) {
                $validator->errors()->add('partner_code', __('api.partner_code_invalid'));
            }

            if (filled($this->input('signup_code')) && ! $this->resolveSignupCode()) {
                $validator->errors()->add('signup_code', __('api.signup_code_invalid'));
            }
        }];
    }

    /** @return array{type: 'partner', partner: PartnerCode}|array{type: 'referral', code: string}|null */
    public function resolveSignupCode(): ?array
    {
        $code = trim((string) $this->input('signup_code'));
        if ($code === '') {
            return null;
        }

        if ($partner = PartnerCode::findActive($code)) {
            return ['type' => 'partner', 'partner' => $partner];
        }

        // Personal reusable codes (customers.referral_code) and legacy
        // pending referral codes both resolve through findInviter().
        if (Setting::get('referral.enabled', true)
            && app(ReferralService::class)->findInviter($code)) {
            return ['type' => 'referral', 'code' => $code];
        }

        return null;
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'The name field is required.',
            'email.required' => 'The email field is required.',
            'email.email' => 'The email must be a valid email address.',
            'email.unique' => 'This email is already registered.',
            'phone.unique' => 'This phone number is already registered.',
            'country_code.regex' => 'The country code must start with + followed by 1-4 digits (e.g. +20).',
            'birthdate.before' => 'The birthdate must be before today.',
            'password.required' => 'The password field is required.',
            'password.min' => 'The password must be at least 8 characters.',
            'password.confirmed' => 'The password confirmation does not match.',
            'password.regex' => 'The password must contain at least one uppercase letter, one lowercase letter, one number, and one special character (#?!@$%^&*-).',
            'avatar.image' => 'The avatar must be an image file.',
            'avatar.max' => 'The avatar must not be larger than 2MB.',
        ];
    }
}
