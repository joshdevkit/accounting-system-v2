<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    private const MAX_ATTEMPTS = 3;

    private const DECAY_SECONDS = 60;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'remember' => ['boolean'],
        ];
    }

    public function validationThrottleKey(): string
    {
        return 'login-validation:' . $this->ip();
    }

    public function throttleKey(): string
    {
        return 'login:' . Str::lower($this->string('email')) . '|' . $this->ip();
    }

    protected function ensureValidationNotRateLimited(): void
    {
        $key = $this->validationThrottleKey();

        if (! RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            return;
        }

        $this->session()->flash(
            'login_retry_after',
            RateLimiter::availableIn($key)
        );

        throw ValidationException::withMessages([
            'email' => 'Too many login attempts.',
        ]);
    }

    public function ensureNotRateLimited(): void
    {
        $key = $this->throttleKey();

        if (! RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            return;
        }

        $this->session()->flash(
            'login_retry_after',
            RateLimiter::availableIn($key)
        );

        throw ValidationException::withMessages([
            'email' => 'Too many login attempts.',
        ]);
    }

    protected function hitValidationRateLimiter(): void
    {
        RateLimiter::hit(
            $this->validationThrottleKey(),
            self::DECAY_SECONDS
        );
    }

    public function hitRateLimiter(): void
    {
        RateLimiter::hit(
            $this->throttleKey(),
            self::DECAY_SECONDS
        );
    }

    public function clearRateLimiter(): void
    {
        RateLimiter::clear($this->throttleKey());
    }

    protected function failedValidation(
        \Illuminate\Contracts\Validation\Validator $validator
    ) {
        $this->ensureValidationNotRateLimited();

        $this->hitValidationRateLimiter();

        parent::failedValidation($validator);
    }
}