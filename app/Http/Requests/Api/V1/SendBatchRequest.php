<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Domain\Notification\Enums\NotificationChannel;
use App\Domain\Notification\Enums\NotificationPriority;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SendBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'channel'          => ['required', 'string', Rule::enum(NotificationChannel::class)],
            'priority'         => ['required', 'string', Rule::enum(NotificationPriority::class)],
            'message_text'     => ['required', 'string', 'min:1', 'max:1000'],
            'recipient_ids'    => ['required', 'array', 'min:1', 'max:1000'],
            'recipient_ids.*'  => ['required', 'uuid', 'distinct'],
        ];
    }

    public function messages(): array
    {
        return [
            'channel.enum'         => 'Channel must be one of: sms, email.',
            'priority.enum'        => 'Priority must be one of: transactional, marketing.',
            'recipient_ids.max'    => 'Maximum 1000 recipients per batch.',
            'recipient_ids.*.uuid' => 'Each recipient ID must be a valid UUID.',
        ];
    }
}
