<?php

namespace App\Enums;

enum NotificationType: string
{
    case CANCEL_REQUEST = 'cancel_request';
    case RESCHEDULE_REQUEST = 'reschedule_request';
    case CANCEL_INFO = 'cancel_info';
    case RESCHEDULE_INFO = 'reschedule_info';
    case CANCEL_APPROVED = 'cancel_approved';
    case CANCEL_REJECTED = 'cancel_rejected';
    case RESCHEDULE_APPROVED = 'reschedule_approved';
    case RESCHEDULE_REJECTED = 'reschedule_rejected';
    case CANCEL_BY_ADMIN = 'cancel_by_admin';
    case RESCHEDULE_BY_ADMIN = 'reschedule_by_admin';
}
