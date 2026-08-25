<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ApprovalActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $action = $this->routeActionName();

        return match ($action) {
            'endorse', 'approve' => [
                'remarks' => ['nullable', 'string', 'max:5000'],
            ],
            'return', 'reject' => [
                'remarks' => ['required', 'string', 'min:4', 'max:5000'],
            ],
            default => [
                'remarks' => ['nullable', 'string', 'max:5000'],
            ],
        };
    }

    private function routeActionName(): string
    {
        $routeName = (string) $this->route()?->getName();

        return match (true) {
            str_contains($routeName, 'endorse') => 'endorse',
            str_contains($routeName, 'approve') => 'approve',
            str_contains($routeName, 'return') => 'return',
            str_contains($routeName, 'reject') => 'reject',
            default => '',
        };
    }
}
